<?php

namespace Vsb\Http\Controllers;

use Log;
use Vsb\Model\InstrumentGroup;
use Vsb\Model\InstrumentGroupPair;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class InstrumentGroupController extends \Illuminate\Routing\Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $igs = InstrumentGroup::with(['pairs'=>function($query){$query->with(['source']);}]);
        $igs = InstrumentGroup::orderBy('id');
        return response()->json($igs->paginate($request->input('per_page',15))->appends(Input::except('page')),200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "name" => 'required',
            "commission" => 'numeric',
            'dayswap' => 'numeric',
            'spread_buy' => 'numeric',
            'spread_sell' => 'numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $ig = InstrumentGroup::create($request->all());
        return response()->json($ig,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\InstrumentGroup  $instrumentGroup
     * @return \Illuminate\Http\Response
     */
    public function show(InstrumentGroup $instrumentGroup)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\InstrumentGroup  $instrumentGroup
     * @return \Illuminate\Http\Response
     */
    public function edit(InstrumentGroup $instrumentGroup)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\InstrumentGroup  $instrumentGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $instrumentGroup)
    {

        $validator = Validator::make($request->all(), [
            // "name" => 'required',
            "commission" => 'numeric',
            'dayswap' => 'numeric',
            'spread_buy' => 'numeric',
            'spread_sell' => 'numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $instrumentGroup = InstrumentGroup::find($instrumentGroup);
        $instrumentGroup->update($request->all());
        if($request->has('pairs')){
            $pairs = is_array($request->pairs)?$request->pairs:[$request->pairs];
            InstrumentGroupPair::where('instrument_group_id',$instrumentGroup->id)->delete();
            $pp = [];
            foreach($pairs as $p)$pp[]=["instrument_group_id"=>$instrumentGroup->id,"instrument_id"=>$p];
            Log::debug('Group pairs',$pp);
            InstrumentGroupPair::insert($pp);
            $instrumentGroup->fresh();
        }
        // $instrumentGroup->load(['pairs']);
        return response()->json($instrumentGroup,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\InstrumentGroup  $instrumentGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy($instrumentGroup)
    {
        $instrumentGroup = InstrumentGroup::find($instrumentGroup);
        InstrumentGroupPair::where('instrument_group_id',$instrumentGroup->id)->delete();
        $instrumentGroup->delete();
    }
}
