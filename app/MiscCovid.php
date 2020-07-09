<?php

namespace App;

use DB;

use Excel;

class MiscCovid extends Common
{


    public static function roche_sample_result($target1, $target2, $error=null)
    {
        $target1 = trim(strtolower($target1));
        $target2 = trim(strtolower($target2));
        $repeatt = 0;

        if($target1 == 'positive'){
            $result = 2;
            $interpretation = 'Positive';
        }
        else if($target2 == 'positive'){
            $result = 2;
            // $interpretation = 'Presumed Positive. Requires Rerun.';
            $interpretation = 'Presumed Positive. New Sample Required to Confirm Results.';
        }
        else if($target1 == 'negative' && $target1 == $target2){
            $result = 1;
            $interpretation = 'Negative';
        }
        else if(in_array($target1, ['invalid', 'negative']) && in_array($target2, ['invalid', 'negative'])){
            $result = 3;
            $interpretation = 'Failed';
            $repeatt = 1;
        }
        else if($target1 == 'valid' && $target1 == $target2){
            $result = 6;
            $interpretation = 'Valid';            
        }
        else{
            $result = 3;
            $interpretation = 'Failed';
            $repeatt = 1;
        }

        // return ['result' => $result, 'interpretation' => $interpretation, 'repeatt' => 0];
        return compact('result', 'interpretation', 'repeatt', 'target1', 'target2', 'error');
    }

    public static function sample_result($interpretation, $error=null)
    {
        $str = strtolower($interpretation);
        $repeatt = 0;

        // if(\Str::contains($str, ['cn', 'dc'])){
        if(preg_match("/[0-9]/", $interpretation)){
            $result = 2;
        }
        else if(\Str::contains($str, ['not']) && \Str::contains($str, ['detected'])){
            $result = 1;
        }
        else{
            $result = 3;
            $interpretation = $error;
            $repeatt = 1;
        }

        return compact('result', 'interpretation', 'repeatt');
    }


    public static function save_repeat($sample_id)
    {
        $original = CovidSample::find($sample_id);
        if($original->run == 5){
            $original->repeatt=0;
            $original->save();
            return false;
        }

        $sample = $original->replicate(['national_sample_id', 'worksheet_id', 'interpretation', 'result', 'repeatt', 'datetested', 'dateapproved', 'dateapproved2', 'approvedby', 'approvedby2']); 
        $sample->run++;
        if($original->parentid == 0) $sample->parentid = $original->id;

        $s = CovidSample::where(['parentid' => $sample->parentid, 'run' => $sample->run])->first();
        if($s) return $s;
        
        $sample->save();
        return $sample;
    }


    public static function get_worksheet_samples($machine_type, $limit, $entered_by=null)
    {
        $machines = Lookup::get_machines();
        $machine = $machines->where('id', $machine_type)->first();

        $user = auth()->user();

        $temp_limit = $limit;     

        if($entered_by){
            $repeats = CovidSampleView::selectRaw("covid_sample_view.*, users.surname, users.oname")
                ->leftJoin('users', 'users.id', '=', 'covid_sample_view.user_id')
                ->where('datereceived', '>', date('Y-m-d', strtotime('-4 months')))
                ->where('parentid', '>', 0)
                ->whereNull('datedispatched')
                ->whereNull('worksheet_id')
                ->whereNull('result')
                ->where(['receivedstatus' => 1, 'covid_sample_view.lab_id' => $user->lab_id, 'repeatt' => 0])
                ->where('site_entry', '!=', 2)
                ->orderBy('covid_sample_view.id', 'desc')
                ->limit($temp_limit)
                ->get();
            $temp_limit -= $repeats->count();
        }

        $samples = CovidSampleView::selectRaw("covid_sample_view.*, users.surname, users.oname")
            ->leftJoin('users', 'users.id', '=', 'covid_sample_view.user_id')
            ->where('datereceived', '>', date('Y-m-d', strtotime('-4 months')))
            ->when($entered_by, function($query) use ($entered_by){
            	$query->where('parentid', 0);
                if(is_array($entered_by)) return $query->whereIn('received_by', $entered_by);
                return $query->where('received_by', $entered_by);
            })
            ->whereNull('datedispatched')
            ->whereNull('worksheet_id')
            ->whereNull('result') 
            ->where(['receivedstatus' => 1, 'covid_sample_view.lab_id' => $user->lab_id, 'repeatt' => 0])
            ->where('site_entry', '!=', 2)  
            ->orderBy('run', 'desc')
            ->orderBy('highpriority', 'desc')
            ->orderBy('datereceived', 'asc')
            ->when(in_array(env('APP_LAB'), [2, 3, 6]), function($query){
                if(in_array(env('APP_LAB'), [3])) return $query->orderBy('justification', 'desc');
                return $query->orderBy('quarantine_site_id')->orderBy('facility_id');
            })
            ->orderBy('covid_sample_view.id', 'asc')     
            ->limit($temp_limit)
            ->get();

        // dd($samples);

        if($entered_by && $repeats->count() > 0) $samples = $repeats->merge($samples);
        $count = $samples->count();        

        $create = false;
        if($count) $create = true;
        // if($count == $limit) $create = true;
        // if(!in_array(env('APP_LAB'), [3]) && $count) $create = true;
        $covid = true;

        return compact('count', 'limit', 'create', 'machine_type', 'machine', 'samples', 'covid');
    }

    public static function dispatch_covid()
    {
        $quarantine_sites = CovidSampleView::selectRaw('distinct quarantine_site_id')
            ->where('datedispatched', '>', date('Y-m-d', strtotime('-2 days')))
            ->where(['repeatt' => 0])
            ->whereNull('date_email_sent')
            ->whereNotNull('datedispatched')
            ->whereNotNull('national_sample_id')
            ->get();

        foreach ($quarantine_sites as $key => $quarantine_site) {
            self::send_results($quarantine_site->quarantine_site_id);
        }
    }


    public static function send_results($quarantine_site_id=null, $facility_id=null)
    {
        $lab = Lab::find(env('APP_LAB'));
        $cc_array = explode(',', $lab->cc_emails);

        $samples = CovidSample::select('covid_samples.*')
            ->join('covid_patients', 'covid_samples.patient_id', '=', 'covid_patients.id')
            ->where('datedispatched', '>', date('Y-m-d', strtotime('-2 days')))
            ->where(['dispatched' => 0, 'repeatt' => 0])
            ->when($facility_id, function($query) use ($facility_id){
                return $query->where('facility_id', $facility_id);
            })
            ->when($quarantine_site_id, function($query) use ($quarantine_site_id){
                return $query->where('quarantine_site_id', $quarantine_site_id);
            })
            ->whereNull('date_email_sent')
            ->whereNotNull('datedispatched')
            ->whereNotNull('national_sample_id')
            ->orderBy('identifier', 'asc')
            ->get();


        $mail_array = [];

        if($quarantine_site_id){
            $quarantine_site = QuarantineSite::find($quarantine_site_id);
            if($quarantine_site && $quarantine_site->email) $mail_array = explode(',', $quarantine_site->email);
        }

        else if($facility_id){
            $quarantine_site = Facility::find($facility_id);
            if($facility && $facility->covid_email) $mail_array = explode(',', $facility->covid_email);
        }
        

        if(!$mail_array && $cc_array){
            Mail::to($cc_array)->send(new CovidDispatch($samples));
        }else{                 
            if($quarantine_site_id){                
                Mail::to($mail_array)->cc($cc_array)->send(new CovidDispatch($samples, $quarantine_site));
            }else if($facility_id){                
                Mail::to($mail_array)->cc($cc_array)->send(new CovidDispatch($samples, $facility));
            }
        }

        foreach ($samples as $key => $sample) {
            $sample->date_email_sent = date('Y-m-d');
            $sample->save();
        }


    }


}
