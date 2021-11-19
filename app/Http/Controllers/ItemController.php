<?php

namespace App\Http\Controllers;

use App\Models\Item;
use \App\Models\AutoBid;
use \App\Events\ItemEvent;
use Illuminate\Http\Request;
use \App\Events\AutoBidEvent;
use \App\Events\ItemWithBidsEvent;
use Illuminate\Support\Facades\Auth;


class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $items = Item::get();
        return $items;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Item $item)
    {
        $itemWithBids = Item::with(['autoBids' => function ($query) use ($item) {
            $query->where('user_id', Auth::id())->where('item_id', $item->id)->where('max_auto_bid', '>', $item->max_bid);
        }])->find($item->id);

        $autoBids = $itemWithBids->autoBids;
        if ($this->checkCount($autoBids)) {
            $item->hasAutoBid = true;
            $item->max_auto_bid = $autoBids[0]->max_auto_bid;
        }
        return $item;
    }


    public function checkCount($items)
    {
        return $items->count() > 0;
    }

    public function cancelAutoBid($autoBid)
    {
        event(new AutoBidEvent($autoBid, 'canceled'));
    }

    public function alertAutoBid($autoBid, $item)
    {
        if ($autoBid->alert_when && $item->max_bid > ($autoBid->alert_when / 100) * $autoBid->max_auto_bid) {
            event(new AutoBidEvent($autoBid, 'warning'));
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Item $item)
    {
        // return;

        $request->validate(
            [
                'max_bid' => 'required|integer|min:' . (int) ($item->max_bid + 1) . '|max:999999999',
            ]
        );

        if ($item->available_untill && now() < $item->available_untill) {
            if ($item->user_id !== Auth::id()) {
                // put auto biiding
                $itemWithBids = Item::with(['autoBids' => function ($query) use ($item) {
                    $query->where('item_id', $item->id)->orderBy('max_auto_bid');
                }])->find($item->id);

                $autoBids =  $itemWithBids->autoBids;
                $lastBid = $autoBids->count() - 1;

                if ($this->checkCount($autoBids) && $lastBid === 0) {
                    $firstBid = $autoBids[0];

                    if ($request->max_bid < $firstBid->max_auto_bid) {
                        $item->max_bid = $request->max_bid + 1;
                        $this->alertAutoBid($firstBid, $item);
                    } else {
                        $item->max_bid = $request->max_bid;
                        $this->cancelAutoBid($firstBid);
                    }
                } else if ($this->checkCount($autoBids) && $lastBid > 0 && $request->max_bid < $autoBids[$lastBid]->max_auto_bid) {

                    $autoBidBrforeLast = $autoBids[$lastBid - 1]->max_auto_bid;

                    if ($request->max_bid < $autoBidBrforeLast) {
                        $item->max_bid = $autoBidBrforeLast + 1;
                        $this->alertAutoBid($autoBids[$lastBid], $item);

                        for ($i = 0; $i < $lastBid - 1; $i++) {
                            $this->cancelAutoBid($autoBids[$i]);
                        }
                    } else {
                        $item->max_bid = $request->max_bid + 1;
                        for ($i = 0; $i < $lastBid; $i++) {
                            $this->cancelAutoBid($autoBids[$i]);
                        }
                    }
                } else {
                    $item->max_bid = $request->max_bid;
                    $item->user_id = Auth::id();

                    for ($i = 0; $i < $lastBid; $i++) {
                        $this->cancelAutoBid($autoBids[$i]);
                    }
                }

                $item->save();

                event(new ItemWithBidsEvent($item));
                return  event(new ItemEvent($item));
            } else {
                return response()->json(['message' => "You have the maximum bid"], 422);
            }
        } else {
            AutoBid::where('item_id', $item->id)->delete();
            return response()->json(['message' => "This bidding is closed"], 422);
        }
    }

    public function autoBid(Request $request, Item $item)
    {
        $request->validate(
            [
                'max_auto_bid' => 'required|integer|min:' . (int) ($item->max_bid + 1) . '|max:999999999',
                'alert_when' => 'integer|min:0|max:100',
            ]
        );
        $autoBid = new AutoBid();
        if (!$autoBid->where('user_id', Auth::id())->where('item_id', $item->id)->first()) {
            $autoBid->max_auto_bid = $request->max_auto_bid;
            $autoBid->alert_when = $request->alert_when;
            $autoBid->item_id = $item->id;
            $autoBid->user_id = Auth::id();
            $autoBid->save();

            $request->merge(['max_bid' => $item->max_bid + 1]);
            $this->update($request, $item);

            $item->hasAutoBid = true;
            $item->max_auto_bid = $autoBid->max_auto_bid;
            return $item;
        } else {
            return response()->json(['message' => "You Can't make auto bid on this item"], 422);
        }
    }
}
