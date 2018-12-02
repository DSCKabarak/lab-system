<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Sample;
use App\Viralsample;
use App\Facility;

class DashboardController extends Controller
{
    //

    public function index($year = null, $month = null)
    {
        if ($year==null || $year=='null'){
            if (session('dashboardYear')==null)
                session(['dashboardYear' => Date('Y')]);
        } else {
            session(['dashboardYear'=>$year]);
        }

        if ($month==null || $month=='null'){
            session()->forget('dashboardMonth');
        } else {
            session(['dashboardMonth'=>(strlen($month)==1) ? '0'.$month : $month]);
        }
        
   		$monthly_test = (object) $this->lab_monthly_tests(session('dashboardYear'));
    	$lab_stats = (object) $this->lab_statistics(session('dashboardYear'), session('dashboardMonth'));
    	$lab_tat_stats = (object) $this->lab_tat_statistics(session('dashboardYear'), session('dashboardMonth'));
    	// dd($lab_stats);
        $year = session('dashboardYear');
        $month = session('dashboardMonth');
        $monthName = "";
        
        if (null !== $month) 
            $monthName = "- ".date("F", mktime(null, null, null, $month));

        $data = (object)['year'=>$year,'month'=>$monthName];
        // dd($data);
    	return view('dashboard.home', ['chart'=>$monthly_test], compact('lab_tat_stats','lab_stats','data'))->with('pageTitle', "Lab Dashboard : $year $monthName");
    }

    public function lab_monthly_tests($year = null)
    {
        $currentTestingSystem = session('testingSystem');
        $result = ($currentTestingSystem == 'Viralload') ? 
                            ['received','tests','rejected','non_suppression'] : 
                            ['tests','positives','negatives','rejected'];
        
        
        foreach ($result as $key => $value) {
            ($value == 'received' || $value == 'rejected') ? $table = "datereceived" : $table = "datetested";
            
            $data[$value] = ($currentTestingSystem == 'Viralload') ? 
                            DB::table('viralsamples')
                                ->selectRaw("MONTH(".$table.") as `month`,MONTHNAME(".$table.") as `monthname`,count(*) as $value")
                                ->when($value, function($query) use ($value){
                                    if ($value == 'received' || $value == 'rejected') {
                                        return $query->join('viralbatches', 'viralbatches.id', '=', 'viralsamples.batch_id');
                                    }
                                })
                                ->when($value, function($query) use ($value){
                                    if($value == 'received'){
                                        return $query->where('receivedstatus', 1);
                                    } else if($value == 'tests'){
                                        return $query->where('result', '<>', NULL);
                                    } else if($value == 'rejected'){
                                        return $query->where('receivedstatus', 2);
                                    }  else if($value == 'non_suppression'){
                                        return $query->where('result', '< LDL copies/ml');
                                    }                
                                })
                                ->where('repeatt', '=', 0)
                                ->whereYear($table, $year)
                                ->groupBy('month', 'monthname')->get() 
                            :
                            DB::table('samples')
                                ->selectRaw("MONTH(".$table.") as `month`,MONTHNAME(".$table.") as `monthname`,count(*) as $value")
                                ->when(($value == 'rejected'), function($query){
                                    return $query->join('batches', 'batches.id', '=', 'samples.batch_id');
                                })
                                ->when($value, function($query) use ($value){
                                    if($value == 'tests'){
                                        return $query->whereRaw('result between 1 and 7');
                                    } else if($value == 'positives'){
                                        return $query->where('result', 2);
                                    } else if($value == 'negatives'){
                                        return $query->where('result', 1);
                                    }  else if($value == 'rejected'){
                                        return $query->where('receivedstatus', 2);
                                    }                
                                }) 
                                ->where('repeatt', '=', 0)
                                ->whereYear($table, $year)
                                ->groupBy('month', 'monthname')->get();
        }
        
        $chartData = self::__mergeMonthlyTests($data);
        $data = [];
        
        if ($currentTestingSystem == 'Viralload') {
            $data['testtrends'][0]['name'] = 'Received';
            $data['testtrends'][1]['name'] = 'Tests';
            $data['testtrends'][2]['name'] = 'Rejected';
            $data['testtrends'][3]['name'] = 'Non-Suppressed';
            
            $data['testtrends'][0]['type'] = $data['testtrends'][1]['type'] = $data['testtrends'][2]['type'] = 'spline';
        } else {
            $data['testtrends'][0]['name'] = 'Rejected';
            $data['testtrends'][1]['name'] = 'Positives';
            $data['testtrends'][2]['name'] = 'Negatives';
            $data['testtrends'][3]['name'] = 'Tests';
            
            $data['testtrends'][0]['type'] = $data['testtrends'][1]['type'] = $data['testtrends'][2]['type'] = 'column';
        }

        $data['testtrends'][3]['type'] = 'spline';

        $data['testtrends'][0]['tooltip'] = $data['testtrends'][1]['tooltip'] = $data['testtrends'][2]['tooltip'] = $data['testtrends'][3]['tooltip'] = ['valueSuffix' => ''];
        
        $data['categories'][0] = 'No Data';
        $data['testtrends'][0]['data'][0] = $data['testtrends'][1]['data'][0] = $data['testtrends'][2]['data'][0] = $data['testtrends'][3]['data'][0] = 0;
        foreach ($chartData as $key => $value) {
            $data['categories'][$key] = $value['monthname'];
            if ($currentTestingSystem == 'Viralload') {
                $data['testtrends'][0]['data'][$key] = (int) $value['received'];
                $data['testtrends'][1]['data'][$key] = (int) $value['tests'];
                $data['testtrends'][2]['data'][$key] = (int) $value['rejected'];
                $data['testtrends'][3]['data'][$key] = (int) $value['non_suppression'];
            } else {
                $data['testtrends'][0]['data'][$key] = (int) $value['rejected'];
                $data['testtrends'][1]['data'][$key] = (int) $value['positives'];
                $data['testtrends'][2]['data'][$key] = (int) $value['negatives'];
                $data['testtrends'][3]['data'][$key] = (int) $value['tests'];
            }
        }
        return $data;
    }

    public function lab_statistics($year = null, $month = null)
    {
        $data = [];
        $current = session('testingSystem');

        $tests = self::__getSamples()->whereRaw("YEAR(datetested) = ".$year)->when($month, function($query)use($month){
                                return $query->whereMonth('datetested', $month);
                            })->count();
        $smsPrinters = Facility::where('smsprinter', '=', 1)
                                            ->where('smsprinterphoneno', '<>', 0)
                                            ->where('lab', '=', Auth()->user()->lab_id)->count();
        $rejection = self::__joinedToBatches()
                            ->when($current, function($query) use ($current, $year, $month){
                                if ($current == 'Viralload') {
                                    return $query->where('viralsamples.receivedstatus', '=', '2')
                                            ->where('viralsamples.repeatt', '=', '0')
                                            ->when($month, function($query) use ($month){
                                                return $query->whereMonth('viralbatches.datereceived', $month);
                                            })
                                            ->whereRaw("YEAR(viralbatches.datereceived) = ".$year);
                                } else {
                                    return $query->where('samples.receivedstatus', '=', '2')
                                            ->where('samples.repeatt', '=', '0')
                                            ->when($month, function($query) use ($month){
                                                return $query->whereMonth('batches.datereceived', $month);
                                            })
                                            ->whereRaw("YEAR(batches.datereceived) = ".$year);
                                }
                            })->count();
        $received = self::__joinedToBatches()
                            ->when($current, function ($query) use ($current, $year, $month) {
                                if ($current == 'Viralload') {
                                    return $query->whereRaw("((viralsamples.parentid=0)||(viralsamples.parentid IS NULL))")
                                                ->when($month, function($query) use ($month){
                                                    return $query->whereMonth('viralbatches.datereceived', $month);
                                                })->whereRaw("YEAR(viralbatches.datereceived) = ".$year);
                                } else {
                                    return $query->whereRaw("YEAR(batches.datereceived) = ".$year)
                                                ->when($month, function($query) use ($month){
                                                    return $query->whereMonth('batches.datereceived', $month);
                                                })->whereRaw("((samples.parentid=0)||(samples.parentid IS NULL))");
                                }
                            })->count();

        $redraws = (session('testingSystem') == 'Viralload') ? 
                        self::__getsampleResultByType(1) : 
                        self::__getsampleResultByType(3) + self::__getsampleResultByType(5);

        
        if (session('testingSystem') == 'Viralload') {
            $typeData = [];
            $types = ['received', 'tested', 'rejected'];
            // $sample_types = DB::table('viralsampletypes')->get();
            foreach ($types as $key => $value) {
                $model = self::__joinedToBatches()->leftJoin('viralsampletype', 'viralsampletype.id', '=', 'viralsamples.sampletype')->when($value, function($query) use ($value, $year, $month){
                        if ($value == 'received' || $value == 'rejected'){
                            $column = 'datereceived';
                            if ($value == 'rejected')
                                $query = $query->where('receivedstatus', '=', 2);
                        }
                        if ($value == 'tested')
                            $column = 'datetested';
                        return $query->whereYear($column, $year)->when($month, function($query) use ($month){
                                                return $query->whereMonth('datetested', $month);
                                            });
                    })->selectRaw("count(*) as `total`, IFNULL(LOWER(`viralsampletype`.`alias`), 'none') as `titles`")
                    ->groupBy('titles')->get();
                foreach ($model as $modelkey => $modelvalue) {
                    $typeData[$value.$modelvalue->titles] = $modelvalue->total;
                }
            }
            
            $data = [
                    'testedSamples' => $tests,
                    'rejectedSamples' => $rejection,
                    'receivedSamples'=> $received,
                    'smsPrinters'   =>  $smsPrinters,
                    'redraws' => $redraws,
                    'nonsuppressed' => self::__getsampleResultByType(3),
                    'suppressed' => self::__getsampleResultByType(2),
                    'totaltestsinlab' => self::__getTotalSamples()->whereRaw("YEAR(viralsamples.datetested) = ".$year)
                                            ->when($month, function($query) use ($month){
                                                return $query->whereMonth('viralsamples.datetested', $month);
                                            })->count(),
                    'sampletypes' => (object)$typeData
                ];
        } else {
            $data = [
                    'testedSamples' =>  $tests,
                    'rejectedSamples'=> $rejection,
                    'receivedSamples'=> $received,
                    'smsPrinters'   =>  $smsPrinters,
                    'redraws'       =>  $redraws,
                    'failedSamples' =>  self::__getsampleResultByType(3),
                    'inconclusive'  =>  self::__getsampleResultByType(5),
                    'positives'     =>  self::__getsampleResultByType(2),
                    'negatives'     =>  self::__getsampleResultByType(1)
                ];
        }
        
    	return $data;
    }

    public function lab_tat_statistics($year=null, $month=null)
    {
        return [
        	'tat1' => self::__getTAT(1),
            'tat2' => self::__getTAT(2),
            'tat3' => self::__getTAT(3),
            'tat4' => self::__getTAT(4),
            'tat5' => self::__getTAT(5)
        ];
    }

    public static function __mergeMonthlyTests($data = null)
    {
        if ($data == null)
            return null;

        $data = (object) $data;
        $newData = [];
        
        // Looping through tests and adding positives, negatives, and rejected
        foreach ($data->tests as $key => $value) {
            $newData[] = [ 'month' => $value->month,'monthname' => $value->monthname,'tests' => $value->tests ];
            if (session('testingSystem') == 'Viralload'){
                $newData[$key] += [ 'received' => 0, 'rejected' => 0, 'non_suppression' => 0 ];

                foreach ($data->received as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['received'] =  (isset($value2->received)) ? $value2->received : 0 ;
                }

                foreach ($data->rejected as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['rejected'] =  (isset($value2->rejected)) ? $value2->rejected : 0 ;
                }

                foreach ($data->non_suppression as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['non_suppression'] =  (isset($value2->non_suppression)) ? $value2->non_suppression : 0 ;
                }
            } else {
                $newData[$key] += [ 'positives' => 0, 'negatives' => 0, 'rejected' => 0 ];

                foreach ($data->positives as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['positives'] =  (isset($value2->positives)) ? $value2->positives : 0 ;
                }

                foreach ($data->negatives as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['negatives'] =  (isset($value2->negatives)) ? $value2->negatives : 0 ;
                }

                foreach ($data->rejected as $key2 => $value2) {
                    if ($value->month == $value2->month)
                        $newData[$key]['rejected'] =  (isset($value2->rejected)) ? $value2->rejected : 0 ;
                }
            }
        }
        
        return $newData;
    }

    public static function __getsampleResultByType($type = null)
    {
        if ($type == null || !is_int($type))
            return 0;
        
        if (session('testingSystem') == 'Viralload') {//First check the system presently in
            $result = [];
            if ($type==1) { //Redraws
                $result = ['Collect New Sample','Failed','Invalid'];
            } else if ($type==2) {//Suppressed
                $result = ['BETWEEN 1 AND  1000','< LDL copies/ml','Target Not Detected','<550','<150','<160','<75','<274','<400',' <400','< 400','<188','<839','<40','<20','<218'];
            } else if ($type==3) {//Non-suppressed
                $result = ['>10000000','BETWEEN 1000 AND  5000','> 1000'];
                // $result = ['> 1000'];
            }
            
            $model = self::__getSamples()
                        ->when($result, function($query) use ($result) {
                            $max = count($result);
                            $max -= 1;
                            foreach ($result as $key => $value) {
                                if ($key == 0){
                                    if (strpos($value, 'BETWEEN') !== false || strpos($value, '>') !== false || strpos($value, '<') !== false){
                                            if (strpos($value, 'LDL') !== false || strpos($value, '>10000000') !== false) {
                                                $query->whereRaw("(viralsamples.result  = '". $value."'");
                                            } else {
                                                $query->whereRaw("(viralsamples.result ".$value);
                                            }
                                        } else {
                                            $query->whereRaw("(viralsamples.result  = '". $value."'");
                                        }
                                } else {
                                    if (strpos($value, 'BETWEEN') !== false || strpos($value, '>') !== false || strpos($value, '<') !== false)
                                    {
                                        if (strpos($value, 'LDL') !== false || strpos($value, '>10000000') !== false) {
                                            $query->orwhereRaw("viralsamples.result  = '". $value."'");
                                        } else {
                                            $query->orwhereRaw("viralsamples.result ".$value);
                                        }
                                    } else {
                                        $query->orwhereRaw("viralsamples.result = '". $value."'");
                                    }
                                }
                                if ($key == $max) {
                                    if (strpos($value, 'BETWEEN') !== false || strpos($value, '>') !== false || strpos($value, '<') !== false)
                                    {
                                        if (strpos($value, 'LDL') !== false || strpos($value, '>10000000') !== false) {
                                            $query->orwhereRaw("viralsamples.result  = '". $value."')");
                                        } else {
                                            $query->orwhereRaw("viralsamples.result ".$value.")");
                                        }
                                    } else {
                                        $query->orwhereRaw("viralsamples.result = '".$value."')");
                                    }
                                } else {
                                    
                                }
                            }
                        });
            
        } else {
            $model = self::__getSamples()
                        ->where('result', '=', $type);
        }
        $year = session('dashboardYear');
        $month = session('dashboardMonth');

    	return 	$model->where('repeatt', '=', '0')
                        ->whereRaw("YEAR(datetested) = ".$year)
                        ->when($month, function($query) use ($month){
                            return $query->whereMonth('datetested', $month);
                        })->count();
    }

    public static function __joinedToBatches()
    {
        (session('testingSystem') == 'Viralload') ?
            $model = DB::table('viralsamples')
                        ->join('viralbatches', 'viralbatches.id', '=', 'viralsamples.batch_id') :
            $model = DB::table('samples')
                        ->join('batches', 'batches.id', '=', 'samples.batch_id')
                        ->where('samples.flag', '=', 1);
    	return $model;
    }


    public static function __getSamples()
    {
        (session('testingSystem') == 'Viralload') ?
            $model = Viralsample::with('batch')->where('result', '<>', '')->where('repeatt', '=', 0)->where('flag', '=', 1) :
            $model = Sample::with('batch')->where('flag', '=', 1);

        return $model;
    }

    public static function __getTotalSamples()
    {
        (session('testingSystem') == 'Viralload') ?
            $model = Viralsample::where('result', '<>', '') :
            $model = Sample::with('batch');

        return $model;
    }

    public static function __getTAT($tat = null)
    {
        $year = session('dashboardYear');
        $month = session('dashboardMonth');

        if(session('testingSystem') == 'Viralload'){
            $model = Viralsample::where('flag', '=', 1);
            $table = 'viralsamples';
        } else if (session('testingSystem') == 'EID') {
            $model = Sample::where('flag', '=', 1);
            $table = 'samples';
        }

        $model = $model->when($tat, function($query) use ($tat) {
                            if($tat == 1)
                                return $query->selectRaw("AVG(tat1) as tatvalues");
                            if($tat == 2)
                                return $query->selectRaw("AVG(tat2) as tatvalues");
                            if($tat == 3)
                                return $query->selectRaw("AVG(tat3) as tatvalues");
                            if($tat == 4)
                                return $query->selectRaw("AVG(tat4) as tatvalues");
                        })->whereRaw("YEAR($table.datetested) = ".$year)
                        ->when($month, function($query) use ($month){
                            return $query->whereMonth("$table.datetested", $month);
                        })->where('repeatt', '=', 0)->first()->tatvalues ?? 0;

        return round($model);
    }
}
