<?php

namespace App\Http\Controllers;

use App\Viralpatient;
use App\Viralsample;
use App\Lookup;
use Illuminate\Http\Request;

class ViralpatientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($facility_id=null)
    {
        $user = auth()->user();
        $facility_user = false;

        if($user->user_type_id == 5) $facility_user=true;

        // if(!$facility_id && !$facility_user) abort(404);

        $patients = Viralpatient::with(['facility'])
        ->withCount(['sample' => function ($query){
            $query->where('repeatt', 0);
        } ])
        ->when($facility_user, function($query) use ($user){
            return $query->where('facility_id', $user->facility_id);
        })
        ->when($facility_id, function($query) use ($facility_id){
            return $query->where('facility_id', $facility_id);
        })
        ->get();

        $data = Lookup::get_viral_lookups();
        $data['patients'] = $patients;
        return view('tables.viralpatients', $data);
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
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Viralpatient  $viralpatient
     * @return \Illuminate\Http\Response
     */
    public function show(Viralpatient $viralpatient)
    {
        $samples = $viralpatient->sample;
        $viralpatient->load(['facility']);
        $samples->load(['batch']);
        $data = Lookup::get_viral_lookups();
        $data['samples'] = $samples;
        $data['patient'] = $viralpatient;

        return view('tables.patient_samples', $data)->with('pageTitle', 'Patient Samples');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Viralpatient  $viralpatient
     * @return \Illuminate\Http\Response
     */
    public function edit(Viralpatient $viralpatient)
    {
        $viralpatient->load(['facility']);
        $data = Lookup::get_viral_lookups();
        $data['patient'] = $viralpatient;

        return view('forms.viralpatients', $data)->with('pageTitle', 'Patient');         
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Viralpatient  $viralpatient
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Viralpatient $viralpatient)
    {
        $viralsamples_arrays = Lookup::viralsamples_arrays();
        $data = $request->only($viralsamples_arrays['patient']);
        $viralpatient->fill($data);
        $viralpatient->pre_update();

        session(['toast_message' => "The patient has been updated."]);

        return redirect('viralpatient/index/' . $viralpatient->facility_id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Viralpatient  $viralpatient
     * @return \Illuminate\Http\Response
     */
    public function destroy(Viralpatient $viralpatient)
    {
        //
    }

    public function merge(Viralpatient $patient)
    {
        $patient->load(['facility']);
        $url = url('viralpatient/search/' . $patient->facility->id);
        $submit_url = url()->current();
        return view('forms.merge_patients', ['patient' => $patient, 'url' => $url, 'submit_url' => $submit_url]);
    }

    public function merge_patients(Request $request, Viralpatient $patient)
    {
        $patients = $request->input('patients');

        $samples = Viralsample::whereIn('patient_id', $patients)->get();

        foreach ($samples as $key => $sample) {
            $sample->patient_id = $patient->id;
            $sample->pre_update();
        }

        $patient_array = Viralpatient::whereIn('id', $patients)->update(['synched' => 3]);

        session(['toast_message' => "The patient records have been merged. The records will be propagated to the national database and then they will be removed."]);

        return redirect('viralpatient/index/' . $patient->facility_id);
    }

    public function transfer(Viralpatient $patient)
    {
        $patient->load(['facility']);
        $submit_url = url()->current();
        return view('forms.transfer_patient', ['patient' => $patient, 'submit_url' => $submit_url]);
    }

    public function transfer_patient(Request $request, Viralpatient $patient)
    {
        $prev_facility_id = $patient->facility_id;
        $patient->facility_id = $request->input('facility_id');
        $patient->pre_update();

        session(['toast_message' => "The patient has been transferred to another facility."]);

        return redirect('viralpatient/index/' . $prev_facility_id);
    }

    public function search(Request $request, $facility_id=null, $female=false)
    {
        $user = auth()->user();
        $facility_user = false;

        if($user->user_type_id == 5) $facility_user=true;
        $string = "(facility_id='{$user->facility_id}')";

        $search = $request->input('search');
        $patients = Viralpatient::select('id', 'patient')
            ->whereRaw("patient like '" . $search . "%'")
            ->when($facility_user, function($query) use ($string){
                return $query->whereRaw($string);
            })
            ->when($facility_id, function($query) use ($facility_id){
                return $query->where('facility_id', $facility_id);
            })
            ->when($female, function($query){
                return $query->where('sex', 2);
            })
            ->paginate(10);
        return $patients;
    }
}
