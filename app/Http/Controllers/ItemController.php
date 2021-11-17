<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \App\Events\ItemEvent;
use \App\Models\AutoBid;


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
        $itemWithBids = Item::with('autoBids')->find($item->id);
        if ($itemWithBids->autoBids->count() > 0) {
            $item->hasAutoBid = true;
        } else {
            $item->hasAutoBid = false;
        }

        return $item;
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

        $request->validate(
            [
                'max_bid' => 'required|integer|min:' . (int) ($item->max_bid + 1) . '|max:999999999',
            ]
        );

        if ($item->available_untill && now() < $item->available_untill) {
            if ($item->user_id !== Auth::id()) {
                // put auto biiding
                $itemWithBids = Item::with('autoBids')->find($item->id);
                $autoBids =  $itemWithBids->autoBids->sortBy('max_auto_bid')->pluck('max_auto_bid');

                $autoBidsCount = $autoBids->count();

                if ($autoBids && $autoBidsCount === 1 && $request->max_bid < $autoBids[0]) {
                    $item->max_bid = $request->max_bid + 1;
                } else if ($autoBids && $autoBidsCount > 1 && $request->max_bid < $autoBids[$autoBidsCount - 1]) {
                    $autoBidBrforeLast = $autoBids[$autoBidsCount - 2];

                    if ($request->max_bid < $autoBidBrforeLast) {
                        $item->max_bid = $autoBidBrforeLast + 1;
                    } else {
                        $item->max_bid = $request->max_bid + 1;
                    }
                } else {
                    $item->max_bid = $request->max_bid;
                    $item->user_id = Auth::id();
                }

                // $item->max_bid = $request->max_bid;
                $item->save();
                return event(new ItemEvent($item));
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
            ]
        );

        $auto_bid = new AutoBid();
        if (!$auto_bid->where('user_id', Auth::id())->where('item_id', $item->id)->first()) {
            $auto_bid->max_auto_bid = $request->max_auto_bid;
            $auto_bid->item_id = $item->id;
            $auto_bid->user_id = Auth::id();
            $auto_bid->save();
            $item->hasAutoBid = true;
            return event(new ItemEvent($item));
        } else {
            return 'no';
        }
    }
}
