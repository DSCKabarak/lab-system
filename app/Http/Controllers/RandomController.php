<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\LabEquipmentTracker;
use App\LabPerformanceTracker;
use App\Sample;
use App\SampleView;
use App\Viralsample;
use App\ViralsampleView;
use App\Random;
use Mpdf\Mpdf;

class RandomController extends Controller
{

	public function refresh_cache()
	{		
		$lookup = \App\Lookup::refresh_cache();
		return back();
	}

	public function refresh_dashboard()
	{		
		$lookup = \App\DashboardCacher::refresh_cache();
		return back();
	}

	public function download_api()
	{		
    	$path = public_path('Lab.postman_collection.json');
    	return response()->download($path);
	}

	public function send_to_login($param = null)
	{
		return redirect('/login');
	}



	public function sysswitch($sys)
	{
		if($sys == 'EID'){
			$new = session(['testingSystem' => 'EID']);
		}else if ($sys == 'Viralload'){
			$new = session(['testingSystem' => 'Viralload']);
		}else if ($sys == 'DR'){
			$new = session(['testingSystem' => 'DR']);
		}else if ($sys == 'CD4'){
			$new = session(['testingSystem' => 'CD4']);
		}
		
		echo json_encode(session('testingSystem'));
	}

	public function search()
	{
		return view('forms.search')->with('pageTitle', 'Search');
	}

	

	public function lablogs($year = null, $month = null){
		if ($month == null || $month=='null') {
			if (null !== session('lablogmonth')) {
				$month = session('lablogmonth');
			} else {
				$currentMonth = date('m');
				$prevMonth = $currentMonth - 1;
				if ($currentMonth == 1)
					$prevMonth = 12;
				$set = session(['lablogmonth' => $prevMonth]);
			}
		} else {
			$set = session(['lablogmonth' => $month]);
		}
		if ($year == null || $year=='null') {
			if(null !== session('lablogyear')) {
				$year = session('lablogyear');
			} else {
				if ($prevMonth == 12)
					$year = date('Y') - 1;
				else
					$year = date('Y');
				$set = session(['lablogyear' => $year]);
			}
		} else {
			$set = session(['lablogyear' => $year]);
		}
		
		$year = session('lablogyear');
		$month = session('lablogmonth');
		$data = Random::__getLablogsData($year, $month);
				
		return view('reports.labtrackers', compact('data'))->with('pageTitle', 'Lab Equipment Log/Tracker');
	}

	public function config()
	{
		return phpinfo();
	}

	public function login_edarp(Request $request) {
		if(auth()->user()) auth()->logout();
        $user = \App\User::find($request->user);
        auth()->login($user);

        if (auth()->check())
        	return redirect()->route('viralsample.nhrl');
        else
        	abort(401);
	}

	public function testlabtracker() {
		$year = date('Y');
    	$month = date('m');
    	$previousMonth =  $month - 1;
    	if ($month == 1) {
    		$previousMonth = 12;
    		$year -= 1;
    	}
    	$data = Random::__getLablogsData($year, $month);
    	$lab = \App\Lab::find(env('APP_LAB'));
    	// dd($lab);

    	$mpdf = new Mpdf();
        $this->lab = \App\Lab::find(env('APP_LAB'));
        $lab = $this->lab;
        $pageData = ['data' => $data, 'lab' => $lab];
        $view_data = view('exports.mpdf_labtracker', $pageData)->render();
        $mpdf->WriteHTML($view_data);
        dd($mpdf);
        $mpdf->Output($this->path, \Mpdf\Output\Destination::FILE);

		// return view('exports.mpdf_labtracker', compact('data', 'lab'));
	}
}
