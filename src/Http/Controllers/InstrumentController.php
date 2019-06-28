<?php

namespace Vsb\Http\Controllers;

use Log;
use Vsb\Model\Instrument;
use Vsb\Model\InstrumentGroup;
use Vsb\Model\InstrumentGroupPair;
use Vsb\Model\InstrumentHistory;
use Vsb\Model\Currency;
use Vsb\Model\Price;
use Vsb\Model\Source;
use Vsb\Model\Histo;
use App\User;
use App\UserTuneHisto;
use App\UserTunePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstrumentController extends \Illuminate\Routing\Controller
{
    public function __construct(){
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexShow(Request $request, $format='json',$id=false){
        $res = Instrument::with([
            'from',
            'to',
            'history',
            'source'
            // 'histo'
        ])->find($id);
        $ret = $res->toArray();
        $ret["histo"] = Histo::where('instrument_id',$res->id)->orderBy('id','desc')->first();
        return ($format=='json')
                ?response()->json($res,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                :view('crm.content.instrument.dashboard',["row"=>$res,'sources'=>Source::all(),'histo'=>Histo::where('instrument_id',$id)->orderBy('id','desc')->first()]);
    }
    public function index(Request $request, $format='json'){
        $user = \get_user($request);
        $ig = InstrumentGroup::find($user->pairgroup);

        $res = Instrument::with([
            'from','to','source'
            // 'from'=>function($q) use ($search){$q->where('code','like','%'.$search.'%');},
            // 'to'=>function($q) use ($search){$q->where('code','like','%'.$search.'%');},
            // 'source'=>function($q) use ($search){$q->where('name','like','%'.$search.'%');}
        ])->whereIn('id',InstrumentGroupPair::where('instrument_group_id',$user->pairgroup)->pluck('instrument_id'))->orderBy('ordering');
        if($request->input('search',false)){
            $c = Currency::where('code','like','%'.$request->search.'%')->select('id')->get();
            if(!is_null($c)){
                $res=$res->where(function($q)use($c){
                    $q->whereIn('from_currency_id',$c)
                        ->orWhereIn('to_currency_id',$c);
                });
            }
        }
        if($request->input('all',false)==false && $request->input('all','0')!='1' ) $res= $res->where('enabled','1');
        if($request->input('grouping',false)!=false)$res=$res->where('grouping',$request->grouping);
        if($request->input('source_id',false)!=false)$res=$res->whereIn('source_id',preg_split('/,/m',$request->source_id));
        if($request->input('currency_id',false)!=false)$res=$res->where(function($q)use($request){
            $q->where('from_currency_id',$request->currency_id)
                ->orWhere('to_currency_id',$request->currency_id);
        });
        $ret = $res->paginate($request->input('per_page',500))->toArray();
        foreach($ret["data"] as &$row){
            // foreach (['dayswap','spread_buy','spread_sell','lot','pips','type','commission'] as $f) {
            foreach (['dayswap','spread_buy','spread_sell','type','commission'] as $f) {
                $row[$f] = $ig->$f;
            }
        }
        return ($format=='json')
                ?response()->json($ret,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                :view('crm.instrument.list',["rows"=>$ret]);
    }
    public function indexes2(Request $request, $format='json',$id=false){
        $res = [];
        $selector = ($id===false)?Instrument::whereNotNull('id'):Instrument::where('id','=',$id);
        // filters {
        // }
        foreach($selector->get() as $row){
            $tsym = Currency::find($row->to_currency_id);
            $fsym = Currency::find($row->from_currency_id);
            $title = $fsym->code."/".$tsym->code;
            $prices =Price::where('instrument_id',$row->id)->orderBy('id', 'desc')->limit(2)->get();
            $histo = Histo::where('instrument_id',$row->id)->orderBy('id', 'desc')->first();
            // $diff =(!is_null($prices) && !empty($price))?(100*floatval($prices[0]->price)/floatval( $prices[1]->price) - 100):0;
            $diff =(!is_null($histo) && !empty($histo))?(100*floatval($histo->close)/floatval( $histo->open) - 100):0;
            $direction = ($diff<0)?-1:1;
            $res[] = [
                "id" => $row->id,
                'title' => $title,
                "diff" => $diff,
                "direction" => $direction,
                "price" =>  $prices[0]->price,
                "histo" =>  $histo,
                "from_currency" => $fsym,
                "to_currency" => $tsym,
                "commission" => $row->commission,
                "enabled" => $row->enabled
            ];
        }
        return response()->json($res,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
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
        //
        $pair = Instrument::create($request->all());
        return response()->json($pair,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Vsb\Crm\Instrument  $instrument
     * @return \Illuminate\Http\Response
     */
    public function show(Instrument $instrument)
    {
        $instrument->load(['source','from','to']);
        return response()->json($instrument,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \Vsb\Crm\Instrument  $instrument
     * @return \Illuminate\Http\Response
     */
    public function edit(Instrument $instrument)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Vsb\Crm\Instrument  $instrument
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$format='json',$id){
        list($res,$code)=[["error"=>"404","message"=>"User {$id} not found."],404];
        try{
            $code = 200;
            $instrument = Instrument::findOrFail($id);
            $ud = $request->all();
            InstrumentHistory::create([
                'instrument_id'=>$instrument->id,
                'old_enabled'=>$instrument->enabled,
                'new_enabled'=>isset($ud['enabled'])?$ud['enabled']:$instrument->enabled,
                'old_commission'=>$instrument->commission,
                'new_commission'=>isset($ud['commission'])?$ud['commission']:$instrument->commission,
            ]);
            Log::debug('pair update data: '.json_encode($ud));
            $instrument->update($ud);
            Log::debug('pair after update: '.json_encode($instrument));
            $res = $instrument;
        }
        catch(\Exception $e){
            $code = 500;
            $res = [
                "error"=>$e->getCode(),
                "message"=>$e->getMessage()
            ];
        }
        return ($format=="json")
            ?response()->json($res,$code,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
            :$this->index($request,$format,$id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Vsb\Crm\Instrument  $instrument
     * @return \Illuminate\Http\Response
     */
    public function destroy(Instrument $instrument)
    {
        //
    }
    public function history(Request $request,$format='json',$id){
        return response()->json(InstrumentHistory::where('instrument_id','=',$id)->orderBy('id','desc')->get(),200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
    public function sources(Request $request){
        return response()->json(Source::get(),200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
