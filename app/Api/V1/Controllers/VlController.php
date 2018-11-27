<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Controllers\BaseController;
use App\Api\V1\Requests\VlRequest;
use App\Api\V1\Requests\VlCompleteRequest;

use App\Lookup;
use App\ViralsampleView;
use App\Viralbatch;
use App\Viralpatient;
use App\Viralsample;

class VlController extends BaseController
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('jwt:auth', []);
    }

    public function vl(VlRequest $request)
    {
        $code = $request->input('mflCode');
        $ccc_number = $request->input('patient_identifier');
        $datecollected = $request->input('datecollected');
        $datereceived = $request->input('datereceived');
        $dob = $request->input('dob');
        $lab = $request->input('lab') ?? env('APP_LAB');

        $facility = Lookup::facility_mfl($code);
        $age = Lookup::calculate_viralage($datecollected, $dob);
        // $sex = Lookup::get_gender($gender);

        $order_no = $request->input('order_no');

        if($order_no){
            $sample_exists = ViralsampleView::where(['order_no' => $order_no])->first();
            if($sample_exists) return $this->response->errorBadRequest("This sample already exists.");
        }

        $sample_exists = ViralsampleView::sample($facility, $ccc_number, $datecollected)->first();
        $fields = Lookup::viralsamples_arrays();

        if($sample_exists){
            return $this->response->errorBadRequest("VL CCC # {$ccc_number} collected on {$datecollected} already exists in database.");
        }

        $batch = Viralbatch::existing($facility, $datereceived, $lab)->withCount(['sample'])->get()->first();

        if($batch && $batch->sample_count < 10){
            unset($batch->sample_count);
        }
        else if($batch && $batch->sample_count > 9){
            unset($batch->sample_count);
            $batch->full_batch();
            $batch = new Viralbatch;
        }
        else{
            $batch = new Viralbatch;
        }
        

        $batch->lab_id = $lab;
        // $batch->user_id = 0;
        $batch->user_id = 66;
        $batch->facility_id = $facility;
        $batch->datereceived = $datereceived;
        $batch->site_entry = 1;
        if($datereceived) $batch->site_entry = 0;
        $batch->save();

        $patient = Viralpatient::existing($facility, $ccc_number)->get()->first();

        if(!$patient){
            $patient = new Viralpatient;
        } 

        $patient->fill($request->only($fields['patient']));
        $patient->patient = $ccc_number;
        $patient->facility_id = $facility;
        $patient->save();

        $sample = new Viralsample;
        $sample->fill($request->only($fields['sample']));
        $sample->justification = Lookup::justification($sample->justification);
        $sample->prophylaxis = Lookup::viral_regimen($sample->prophylaxis);
        $sample->batch_id = $batch->id;
        $sample->patient_id = $patient->id;
        $sample->age = $age;
        if($datereceived) $sample->receivedstatus = 1;
        $sample->save();

        $sample->load(['patient', 'batch']);
        return $sample;

    }

    public function complete_result(VlCompleteRequest $request)
    {
        $editted = $request->input('editted');
        $lab = $request->input('lab') ?? env('APP_LAB');
        $code = $request->input('mflCode');
        $specimenlabelID = $request->input('specimenlabelID');
        $patient_identifier = $request->input('patient_identifier');
        $datecollected = $request->input('datecollected');
        $datereceived = $request->input('datereceived');
        $datedispatched = $request->input('datedispatched');
        $dob = $request->input('dob');
        // $sex = Lookup::get_gender($gender);
        
        $facility = Lookup::facility_mfl($code);
        $age = Lookup::calculate_viralage($datecollected, $dob);

        $order_no = $request->input('order_no');

        if($order_no){
            $sample_exists = ViralsampleView::where(['order_no' => $order_no])->first();
            if($sample_exists && !$editted) return $this->response->errorBadRequest("This sample already exists.");
        }

        $sample_exists = ViralsampleView::sample($facility, $patient_identifier, $datecollected)->first();
        $fields = Lookup::viralsamples_arrays();

        if($sample_exists && !$editted){
            return $this->response->errorBadRequest("VL CCC # {$patient_identifier} collected on {$datecollected} already exists in database.");
        }

        if(!$editted){
            $batch = Viralbatch::existing($facility, $datereceived, $lab)->withCount(['sample'])->get()->first();

            if($batch && $batch->sample_count < 10){
                unset($batch->sample_count);
            }
            else if($batch && $batch->sample_count > 9){
                unset($batch->sample_count);
                $batch->full_batch();
                $batch = new Viralbatch;
            }
            else{
                $batch = new Viralbatch;
            }

            $batch->lab_id = $lab;
            $batch->user_id = 0;
            $batch->facility_id = $facility;
            $batch->datereceived = $datereceived;
            $batch->datedispatched = $datedispatched;
            $batch->site_entry = 0;
            $batch->edarp();            
        }

        $patient = Viralpatient::existing($facility, $patient_identifier)->get()->first();

        if(!$patient){
            $patient = new Viralpatient;
        } 

        $patient->fill($request->only($fields['patient'])); 
        $patient->patient = $patient_identifier;
        $patient->facility_id = $facility;
        $patient->edarp();

        if($editted){
            $sample = Viralsample::find($sample_exists->id);

            $batch = $sample->batch;
            $batch->facility_id = $facility;
            $batch->datereceived = $datereceived;
            $batch->datedispatched = $datedispatched;
            $batch->site_entry = 0;
            $batch->edarp();            
        }
        else{
            $sample = new Viralsample;
            $sample->batch_id = $batch->id;
            $sample->patient_id = $patient->id;
        }

        $sample->fill($request->only($fields['sample_api']));
        $sample->justification = Lookup::justification($sample->justification);
        $sample->prophylaxis = Lookup::viral_regimen($sample->prophylaxis);
        $sample->age = $age;
        $sample->comments = $specimenlabelID;
        $sample->dateapproved = $sample->dateapproved2 = $sample->datetested;
        $sample->edarp();

        $sample->load(['patient', 'batch']);
        return $sample;
    }




}
