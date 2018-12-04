<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Controllers\BaseController;
use App\Api\V1\Requests\EidRequest;
use App\Api\V1\Requests\EidCompleteRequest;

use App\Lookup;
use App\SampleView;
use App\Batch;
use App\Patient;
use App\Sample;
use App\Mother;

class EidController extends BaseController
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

    public function eid(EidRequest $request)
    {
        $code = $request->input('mflCode');
        $mother_ccc = $request->input('ccc_no');
        $hiv_status = $request->input('hiv_status');
        $hei_number = $request->input('patient_identifier');

        $datereceived = $request->input('datereceived');
        $datecollected = $request->input('datecollected');
        $dob = $request->input('dob');
        $mother_age = $request->input('mother_age');

        $facility = Lookup::facility_mfl($code);
        $age = Lookup::calculate_age($datecollected, $dob);
        $lab = $request->input('lab') ?? env('APP_LAB');

        $sample_exists = SampleView::sample($facility, $hei_number, $datecollected)->get()->first();
        $fields = Lookup::samples_arrays();

        if($sample_exists){
            return $this->response->errorBadRequest("EID HEI Number {$hei_number} collected on {$datecollected} already exists in database.");
        }

        $order_no = $request->input('order_no');

        if($order_no){
            $sample_exists = SampleView::where(['order_no' => $order_no])->first();
            if($sample_exists) return $this->response->errorBadRequest("This sample already exists.");
        }

        

        $batch = Batch::existing($facility, $datereceived, $lab)->withCount(['sample'])->get()->first();

        if($batch && $batch->sample_count < 10){
            unset($batch->sample_count);
        }
        else if($batch && $batch->sample_count > 9){
            unset($batch->sample_count);
            $batch->full_batch();
            $batch = new Batch;
        }
        else{
            $batch = new Batch;
        }

        $batch->lab_id = $lab;
        $batch->facility_id = $facility;
        $batch->datereceived = $datereceived;
        if($batch->facility_id == 4840 && !$batch->datereceived) $batch->datereceived = date('Y-m-d');
        $batch->user_id = 66;
        // if(env('APP_LAB') == 5) $batch->user_id = 66;
        $batch->input_complete = 1;
        $batch->site_entry = 1;
        if($datereceived || $batch->facility_id == 4840) $batch->site_entry = 0;
        $batch->save();

        $patient = Patient::existing($facility, $hei_number)->get()->first();

        if($patient){
            $mom = $patient->mother;
        } 
        else{
            $patient = new Patient;
            $mom = new Mother;
        }

        $mom->ccc_no = $mother_ccc;
        $mom->facility_id = $facility;
        $mom->hiv_status = $hiv_status;
        $mom->mother_dob = Lookup::calculate_mother_dob($datecollected, $mother_age);
        $mom->save();
        
        $patient->fill($request->only($fields['patient']));
        $patient->mother_id = $mom->id;
        $patient->patient = $hei_number;
        $patient->facility_id = $facility;
        $patient->save();

        $sample = new Sample;
        $sample->fill($request->only($fields['sample']));

        if(!$sample->pcrtype){

            $prev_samples = Sample::where(['patient_id' => $patient->id, 'repeatt' => 0])->orderBy('datetested', 'asc')->get();
            $previous_positive = 0;
            $recommended_pcr = 1;

            if($prev_samples->count() > 0){
                $pos_sample = $prev_samples->where('result', 2)->first();
                if($pos_sample){
                    $previous_positive = 1;
                    $recommended_pcr = 4;

                    $bool = false;
                    foreach ($prev_samples as $key => $sample) {
                        if($sample->result == 2) $bool = true;
                        if($bool && $sample->result == 1) $recommended_pcr = 5;
                    }
                }
                else{
                    if($age < 12){
                        $recommended_pcr = 2;
                    }
                    else{
                        $recommended_pcr = 3;
                    }
                }                
            }
            $sample->pcrtype = $recommended_pcr;
        }

        $sample->regimen = Lookup::eid_regimen($sample->regimen);
        $sample->mother_prophylaxis = Lookup::eid_intervention($sample->mother_prophylaxis);
        $sample->batch_id = $batch->id;
        $sample->patient_id = $patient->id;
        $sample->age = $age;
        if($datereceived || $batch->facility_id == 4840) $sample->receivedstatus = 1;
        $sample->save();

        $sample->load(['patient.mother', 'batch']);
        return $sample;
    }

    public function complete_result(EidCompleteRequest $request)
    {
        $editted = $request->input('editted');
        $lab = $request->input('lab') ?? env('APP_LAB');
        $code = $request->input('mflCode');
        $specimenlabelID = $request->input('specimenlabelID');
        $patient_identifier = $request->input('patient_identifier');
        $datecollected = $request->input('datecollected');
        $datereceived = $request->input('datereceived');
        $datedispatched = $request->input('datedispatched');
        // $gender = $request->input('gender');
        $dob = $request->input('dob');
        $mother_age = $request->input('mother_age');
        
        $ccc_no = $request->input('ccc_no');
        $hiv_status = $request->input('hiv_status');

        $facility = Lookup::facility_mfl($code);
        $age = Lookup::calculate_age($datecollected, $dob);
        // $sex = Lookup::get_gender($gender);

        $order_no = $request->input('order_no');

        if($order_no){
            $sample_exists = SampleView::where(['order_no' => $order_no])->first();
            if($sample_exists && !$editted) return $this->response->errorBadRequest("This sample already exists.");
        }

        $sample_exists = SampleView::sample($facility, $patient_identifier, $datecollected)->first();
        $fields = Lookup::samples_arrays();

        if($sample_exists && !$editted){
            return $this->response->errorBadRequest("EID HEI Number {$patient_identifier} collected on {$datecollected} already exists in database.");
        }

        if(!$editted){
            $batch = Batch::existing($facility, $datereceived, $lab)->withCount(['sample'])->get()->first();

            if($batch && $batch->sample_count < 10){
                unset($batch->sample_count);
            }
            else if($batch && $batch->sample_count > 9){
                unset($batch->sample_count);
                $batch->full_batch();
                $batch = new Batch;
            }
            else{
                $batch = new Batch;
            }

            $batch->lab_id = $lab;
            $batch->user_id = 0;
            $batch->facility_id = $facility;
            $batch->datereceived = $datereceived;
            $batch->datedispatched = $datedispatched;
            $batch->site_entry = 0;
            $batch->edarp();            
        }

        $patient = Patient::existing($facility, $patient_identifier)->get()->first();

        if($patient){
            $mom = $patient->mother;
        } 
        else{
            $patient = new Patient;
            $mom = new Mother;
        }

        $mom->ccc_no = $ccc_no;
        $mom->mother_dob = Lookup::calculate_mother_dob($datecollected, $mother_age);
        $mom->facility_id = $facility;
        $mom->hiv_status = $hiv_status;
        $mom->edarp();

        $patient->fill($request->only($fields['patient']));
        $patient->mother_id = $mom->id;
        $patient->patient = $patient_identifier;
        $patient->facility_id = $facility;
        $patient->edarp();

        if($editted){
            $sample = Sample::find($sample_exists->id);

            $batch = $sample->batch;
            $batch->facility_id = $facility;
            $batch->datereceived = $datereceived;
            $batch->datedispatched = $datedispatched;
            $batch->site_entry = 0;
            $batch->pre_update();
        }
        else{
            $sample = new Sample;
            $sample->batch_id = $batch->id;
            $sample->patient_id = $patient->id;
        }

        $sample->fill($request->only($fields['sample_api']));
        $sample->regimen = Lookup::eid_regimen($sample->regimen);
        $sample->mother_prophylaxis = Lookup::eid_intervention($sample->mother_prophylaxis);
        $sample->age = $age;
        $sample->comments = $specimenlabelID;
        $sample->dateapproved = $sample->dateapproved2 = $sample->datetested;
        $sample->edarp();

        $sample->load(['patient.mother', 'batch']);
        return $sample;
    }




}
