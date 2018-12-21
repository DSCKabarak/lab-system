<?php

namespace App\Http\Controllers;

use App\Facility;
use App\Batch;
use App\Sample;
use App\Misc;
use App\Lookup;
use App\DashboardCacher as Refresh;


use Mpdf\Mpdf;
use Excel;


use App\Mail\EidDispatch;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    
    public function index($batch_complete=4, $date_start=NULL, $date_end=NULL, $facility_id=NULL, $subcounty_id=NULL, $partner_id=NULL)
    {
        $user = auth()->user();
        $facility_user = false;
        $date_column = "batches.datereceived";
        if($batch_complete == 1) $date_column = "batches.datedispatched";
        if($user->user_type_id == 5) $facility_user=true;

        $s_facility_id = session()->pull('facility_search');
        if($s_facility_id){ 
            $myurl = url("batch/facility/{$facility_id}/{$batch_complete}"); 
            $myurl2 = url("batch/facility/{$facility_id}"); 
        }
        else{ 
            $myurl =  url('batch/index/' . $batch_complete); 
            $myurl2 = url('batch/index'); 
        }

        $string = "(user_id='{$user->id}' OR batches.facility_id='{$user->facility_id}')";

        $batches = Batch::select(['batches.*', 'facilitys.name', 'users.surname', 'users.oname'])
            ->leftJoin('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->leftJoin('users', 'users.id', '=', 'batches.user_id')
            ->when($date_start, function($query) use ($date_column, $date_start, $date_end){
                if($date_end)
                {
                    return $query->whereDate($date_column, '>=', $date_start)
                    ->whereDate($date_column, '<=', $date_end);
                }
                return $query->whereDate($date_column, $date_start);
            })
            ->when(true, function($query) use ($user, $string){
                if($user->user_type_id == 5) return $query->whereRaw($string);
                return $query->where('batches.lab_id', $user->lab_id)->where('site_entry', '!=', 2);
            })
            ->when($facility_id, function($query) use ($facility_id){
                return $query->where('batches.facility_id', $facility_id);
            })
            ->when($subcounty_id, function($query) use ($subcounty_id){
                return $query->where('facilitys.district', $subcounty_id);
            })
            ->when($partner_id, function($query) use ($partner_id){
                return $query->where('facilitys.partner', $partner_id);
            })
            ->when(true, function($query) use ($batch_complete){
                if($batch_complete < 4) return $query->where('batch_complete', $batch_complete);

                else if($batch_complete == 5){
                    return $query->whereNull('datereceived')
                        ->where(['site_entry' => 1, 'batch_complete' => 0])
                        ->where('batches.created_at', '<', date('Y-m-d', strtotime('-10 days')));
                }
            })
            ->when(true, function($query) use ($batch_complete){
                if($batch_complete == 1) return $query->orderBy('batches.datedispatched', 'desc');
                return $query->orderBy('batches.created_at', 'desc');
            })
            ->where('batches.lab_id', env('APP_LAB'))
            ->paginate();

        $this->batches_transformer($batches);

        if($batch_complete == 1){
            $p = Lookup::get_partners();
            $fac = false;
            if($facility_id) $fac = Facility::find($facility_id);
            return view('tables.dispatched_batches', [
                'batches' => $batches, 'myurl' => $myurl, 'myurl2' => $myurl2, 'pre' => '', 
                'batch_complete' => $batch_complete, 
                'partners' => $p['partners'], 'subcounties' => $p['subcounties'], 
                'partner_id' => $partner_id, 'subcounty_id' => $subcounty_id, 'facility' => $fac])
                    ->with('pageTitle', 'Samples by Batch');
        }

        return view('tables.batches', ['batches' => $batches, 'myurl' => $myurl, 'myurl2' => $myurl2, 'pre' => '', 'batch_complete' => $batch_complete])->with('pageTitle', 'Samples by Batch');
    }
    
    public function to_print($date_start=NULL, $date_end=NULL, $facility_id=NULL, $subcounty_id=NULL, $partner_id=NULL)
    {
        $user = auth()->user();
        $facility_user = false;
        $date_column = "batches.datedispatched";
        if($user->user_type_id == 5) $facility_user=true;

        $s_facility_id = session()->pull('facility_search');
        if($s_facility_id){ 
            $myurl = url("batch/facility/{$facility_id}/{$batch_complete}"); 
            $myurl2 = url("batch/facility/{$facility_id}"); 
        }
        else{ 
            $myurl = $myurl2 =  url('batch/to_print/'); 
        }

        $string = "(user_id='{$user->id}' OR batches.facility_id='{$user->facility_id}')";

        $batches = Batch::select(['batches.*', 'facilitys.name', 'users.surname', 'users.oname'])
            ->leftJoin('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->leftJoin('users', 'users.id', '=', 'batches.user_id')
            ->where('batch_complete', 1)
            ->whereRaw('(datebatchprinted is null or dateindividualresultprinted is null)')
            ->when($date_start, function($query) use ($date_column, $date_start, $date_end){
                if($date_end)
                {
                    return $query->whereDate($date_column, '>=', $date_start)
                    ->whereDate($date_column, '<=', $date_end);
                }
                return $query->whereDate($date_column, $date_start);
            })
            ->when($facility_user, function($query) use ($string){
                return $query->whereRaw($string);
            })
            ->when($facility_id, function($query) use ($facility_id){
                return $query->where('batches.facility_id', $facility_id);
            })
            ->when($subcounty_id, function($query) use ($subcounty_id){
                return $query->where('facilitys.district', $subcounty_id);
            })
            ->when($partner_id, function($query) use ($partner_id){
                return $query->where('facilitys.partner', $partner_id);
            })
            ->when(true, function($query) use ($facility_user){
                if(!$facility_user) return $query->where('site_entry', '!=', 2);
            })
            ->orderBy('batches.datedispatched', 'desc')
            ->paginate(50);

        $this->batches_transformer($batches);

        $p = Lookup::get_partners();
        $fac = false;
        if($facility_id) $fac = Facility::find($facility_id);
        return view('tables.dispatched_batches', [
            'batches' => $batches, 'myurl' => $myurl, 'myurl2' => $myurl2, 'pre' => '', 
            'batch_complete' => 1, 'to_print' => true, 
            'partners' => $p['partners'], 'subcounties' => $p['subcounties'], 
            'partner_id' => $partner_id, 'subcounty_id' => $subcounty_id, 'facility' => $fac])
                ->with('pageTitle', 'Samples by Batch');
    }

    public function delayed_batches()
    {
        $batches = Batch::selectRaw("batches.*, COUNT(samples.id) AS `samples_count`, facilitys.name, users.surname, users.oname")
            ->leftJoin('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->leftJoin('users', 'users.id', '=', 'batches.user_id')
            ->join('samples', 'batches.id', '=', 'samples.batch_id')
            ->where('batch_complete', 0)
            ->when(true, function($query){
                if(in_array(env('APP_LAB'), \App\Lookup::$double_approval)){
                    return $query->whereRaw("( receivedstatus=2 OR  (result > 0 AND (repeatt = 0 or repeatt is null) AND approvedby IS NOT NULL AND approvedby2 IS NOT NULL) )");
                }
                return $query->whereRaw("( receivedstatus=2 OR  (result > 0 AND (repeatt = 0 or repeatt is null) AND approvedby IS NOT NULL) )");
            })
            ->groupBy('batches.id')
            // ->having('samples_count', '>', 0)
            ->havingRaw('COUNT(samples.id) > 0')
            ->paginate();

        $this->batches_transformer($batches);

        return view('tables.batches', ['batches' => $batches, 'display_delayed' => true, 'pre' => '', ])->with('pageTitle', 'Delayed Batches');
    }

    public function facility_batches($facility_id, $batch_complete=4, $date_start=NULL, $date_end=NULL)
    {
        session(['facility_search' => $facility_id]);
        return $this->index($batch_complete, $date_start, $date_end, $facility_id);
    }

    public function batch_search(Request $request)
    {
        $submit_type = $request->input('submit_type');
        $to_print = $request->input('to_print');
        $date_start = $request->input('from_date', 0);
        if($submit_type == 'submit_date') $date_start = $request->input('filter_date', 0);
        $date_end = $request->input('to_date', 0);

        if($date_start == '') $date_start = 0;
        if($date_end == '') $date_end = 0;

        $partner_id = $request->input('partner_id', 0);
        $subcounty_id = $request->input('subcounty_id', 0);
        $facility_id = $request->input('facility_id', 0);

        if($partner_id == '') $partner_id = 0;
        if($subcounty_id == '') $subcounty_id = 0;
        if($facility_id == '') $facility_id = 0;

        if($submit_type == 'excel') return $this->dispatch_report($date_start, $date_end, $facility_id, $subcounty_id, $partner_id);

        if($to_print) return redirect("batch/to_print/{$date_start}/{$date_end}/{$facility_id}/{$subcounty_id}/{$partner_id}");

        return redirect("batch/index/1/{$date_start}/{$date_end}/{$facility_id}/{$subcounty_id}/{$partner_id}");
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
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function show(Batch $batch)
    {
        $samples = $batch->sample;
        $samples->load(['patient.mother']);
        $batch->load(['view_facility', 'receiver', 'creator.facility']);
        $data = Lookup::get_lookups();
        $data['batch'] = $batch;
        $data['samples'] = $samples;

        return view('tables.batch_details', $data)->with('pageTitle', 'Batches');
    }

    public function transfer(Batch $batch)
    {
        $samples = $batch->sample;
        $samples->load(['patient.mother']);
        $batch->load(['view_facility', 'receiver', 'creator.facility']);
        $data = Lookup::get_lookups();
        $data['batch'] = $batch;
        $data['samples'] = $samples;

        return view('tables.transfer_batch_samples', $data)->with('pageTitle', 'Transfer Samples');
    }

    public function transfer_to_new_batch(Request $request, Batch $batch)
    {
        $sample_ids = $request->input('samples');
        $submit_type = $request->input('submit_type');

        if(!$sample_ids){
            session(['toast_message' => "No samples have been selected."]);
            session(['toast_error' => 1]);
            return back();            
        }

        $new_batch = new Batch;
        $new_batch->fill($batch->replicate(['synched', 'batch_full'])->toArray());
        if($submit_type != "new_facility"){
            $new_batch->id = (int) $batch->id + 0.5;
            $new_id = $batch->id + 0.5;
            if($new_batch->id == floor($new_batch->id)){
                session(['toast_message' => "The batch {$batch->id} cannot have its samples transferred."]);
                session(['toast_error' => 1]);
                return back();
            }    
        }
        $new_batch->created_at = $batch->created_at;
        $new_batch->save();

        if($submit_type == "new_facility") $new_id = $new_batch->id;

        $count = 0;
        $s;

        $has_received_status = false;

        foreach ($sample_ids as $key => $id) {
            $sample = Sample::find($id);
            if($submit_type == "new_batch" && ($sample->receivedstatus == 2 || ($sample->repeatt == 0 && $sample->result ))){
                continue;
            }else{
                $parent = $sample->parent;
                if($parent){
                    $parent->batch_id = $new_id;
                    $parent->pre_update();

                    $children = $parent->children;
                    if($children){
                        foreach ($children as $child) {
                            $child->batch_id = $new_id;
                            $child->pre_update();
                        }                        
                    }
                }
            }
            if($sample->result && $submit_type == "new_batch") continue;
            if($sample->receivedstatus) $has_received_status = true;
            $sample->batch_id = $new_id;
            $sample->pre_update();
            $s = $sample;
            $count++;
        }
        // $s = $new_batch->sample->first();

        if(!$has_received_status){
            $new_batch->datereceived = null;
            $new_batch->save();
        }

        Misc::check_batch($batch->id);
        Misc::check_batch($new_id);

        session(['toast_message' => "The batch {$batch->id} has had {$count} samples transferred to  batch {$new_id}."]);
        if($submit_type == "new_facility"){
            session(['toast_message' => "The batch {$batch->id} has had {$count} samples transferred to  batch {$new_id}. Update the facility on this form to complete the process."]);
            return redirect('sample/' . $s->id . '/edit');
        }
        return redirect('batch/' . $new_batch->id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function edit(Batch $batch)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Batch $batch)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function destroy(Batch $batch)
    {
        if(!$batch->delete_button) abort(409, "This batch is not eligible for deletion.");
        Sample::where(['batch_id' => $batch->id])->delete();
        $batch->delete();
        session(['toast_message' => "Batch {$batch->id} has been deleted."]);
        return back();
    }

    public function destroy_multiple(Request $request)
    {
        $batches = $request->input('batches');

        foreach ($batches as $id) {
            $batch = Batch::find($id);
            $batch->batch_delete();
        }
        session(['toast_message' => "The selected batches have been deleted."]);
        return back();
    }

    public function batch_dispatch()
    {
        return $this->get_rows();
    }


    public function confirm_dispatch(Request $request)
    {
        $batches = $request->input('batches');
        $final_dispatch = $request->input('final_dispatch');
        if (empty($batches)){
            session(['toast_message' => "No batch selected<br /><br />Please select a batch",
                'toast_error' => 1]);
            return redirect('/batch/dispatch');
            // return redirect('/viralbatch/complete_dispatch');
        }
        if(!$final_dispatch) return $this->get_rows($batches);

        foreach ($batches as $key => $value) {
            $batch = Batch::find($value);
            $facility = Facility::find($batch->facility_id);

            // if(!$batch->sent_email){ 
            //     $batch->sent_email = true;
            //     $batch->dateemailsent = date('Y-m-d');
            // }
            $batch->datedispatched = date('Y-m-d');
            $batch->batch_complete = 1;
            $batch->pre_update();


            // if($facility->email != null || $facility->email != '')
            // {
            //     $mail_array = array('joelkith@gmail.com', 'tngugi@gmail.com', 'baksajoshua09@gmail.com');
            //     if(env('APP_ENV') == 'production') $mail_array = $facility->email_array;
            //     Mail::to($mail_array)->cc(['joel.kithinji@dataposit.co.ke', 'joshua.bakasa@dataposit.co.ke'])->send(new EidDispatch($batch));
            // }         
        }
        Refresh::refresh_cache();
        // Batch::whereIn('id', $batches)->update(['datedispatched' => date('Y-m-d'), 'batch_complete' => 1]);

        return redirect('/batch/index/1');
    }

    public function get_rows($batch_list=NULL)
    {
        $batches = Batch::select('batches.*', 'facility_contacts.email', 'facilitys.name')
            ->join('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->join('facility_contacts', 'facilitys.id', '=', 'facility_contacts.facility_id')
            ->when($batch_list, function($query) use ($batch_list){
                return $query->whereIn('batches.id', $batch_list);
            })
            ->where('batch_complete', 2)
            ->get();

        $subtotals = Misc::get_subtotals();
        $rejected = Misc::get_rejected();
        $date_modified = Misc::get_maxdatemodified();
        $date_tested = Misc::get_maxdatetested();

        $batches->transform(function($batch, $key) use ($subtotals, $rejected, $date_modified, $date_tested){
            $neg = $subtotals->where('batch_id', $batch->id)->where('result', 1)->first()->totals ?? 0;
            $pos = $subtotals->where('batch_id', $batch->id)->where('result', 2)->first()->totals ?? 0;
            $failed = $subtotals->where('batch_id', $batch->id)->where('result', 3)->first()->totals ?? 0;
            $redraw = $subtotals->where('batch_id', $batch->id)->where('result', 5)->first()->totals ?? 0;
            $noresult = $subtotals->where('batch_id', $batch->id)->where('result', 0)->first()->totals ?? 0;

            $rej = $rejected->where('batch_id', $batch->id)->first()->totals ?? 0;
            $total = $neg + $pos + $failed + $redraw + $noresult + $rej;

            $dm = $date_modified->where('batch_id', $batch->id)->first()->mydate ?? '';
            $dt = $date_tested->where('batch_id', $batch->id)->first()->mydate ?? '';

            $batch->negatives = $neg;
            $batch->positives = $pos;
            $batch->failed = $failed;
            $batch->redraw = $redraw;
            $batch->noresult = $noresult;
            $batch->rejected = $rej;
            $batch->total = $total;
            $batch->date_modified = $dm;
            $batch->date_tested = $dt;
            return $batch;
        });

        return view('tables.dispatch', ['batches' => $batches, 'pending' => $batches->count(), 'batch_list' => $batch_list, 'pageTitle' => 'Batch Dispatch']);
    }


    public function approve_site_entry()
    {
        $batches = Batch::selectRaw("batches.*, COUNT(samples.id) AS sample_count, facilitys.name, creator.name as creator")
            ->leftJoin('samples', 'batches.id', '=', 'samples.batch_id')
            ->leftJoin('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->leftJoin('facilitys as creator', 'creator.id', '=', 'batches.user_id')
            ->whereNull('receivedstatus')
            ->where('site_entry', 1)
            ->groupBy('batches.id')
            ->get();

        // $batches->setPath(url()->current());

        $batch_ids = $batches->pluck(['id'])->toArray();
        $subtotals = Misc::get_subtotals($batch_ids, false);
        $rejected = Misc::get_rejected($batch_ids, false);

        $batches->transform(function($batch, $key) use ($subtotals, $rejected){

            $noresult = $subtotals->where('batch_id', $batch->id)->where('result', 0)->first()->totals ?? 0;
            $rej = $rejected->where('batch_id', $batch->id)->first()->totals ?? 0;
            $total = $noresult + $rej;

            $batch->delays = '';
            $batch->datecreated = $batch->my_date_format('created_at');
            $batch->datereceived = $batch->my_date_format('datereceived');
            $batch->total = $total;
            $batch->rejected = $rej;
            $batch->result = '';
            $batch->noresult = $noresult;
            $batch->status = $batch->batch_complete;
            $batch->approval = true;
            return $batch;
        });

        return view('tables.batches', ['batches' => $batches, 'site_approval' => true, 'pre' => '', 'datatable'=>true])->with('pageTitle','Site Approval');
    }



    public function site_entry_approval(Batch $batch)
    {
        $sample = Sample::where('batch_id', $batch->id)->whereNull('receivedstatus')->get()->first();

        if($sample){
            session(['site_entry_approval' => true]);
            $sample->load(['patient.mother', 'batch']);
            $data = Lookup::samples_form();
            $data['sample'] = $sample;
            $data['site_entry_approval'] = true;
            return view('forms.samples', $data); 
        }
        else{
            $batch->received_by = auth()->user()->id;
            $batch->save();
            session(['toast_message' => "All the samples in the batch have been received."]);
            Refresh::refresh_cache();
            return redirect('batch/site_approval');
        }
    }


    public function site_entry_approval_group(Batch $batch)
    {
        $samples = Sample::with(['patient.mother'])->where('batch_id', $batch->id)->whereRaw("receivedstatus is null or receivedstatus=0")->get();

        if($samples->count() > 0){            
            $data = Lookup::samples_form();
            $batch->load(['creator.facility', 'view_facility']);
            $data['batch'] = $batch;
            $data['samples'] = $samples;
            $data['pageTitle'] = "Approve batch";
            return view('forms.approve_batch', $data);
        }
        else{
            session(['toast_message' => "All the samples in the batch have been received."]);
            return redirect('batch/site_approval');
        }
    }

    public function site_entry_approval_group_save(Request $request, Batch $batch)
    {
        $sample_ids = $request->input('samples');
        $rejectedreason_array = $request->input('rejectedreason');
        $spots_array = $request->input('spots');
        $submit_type = $request->input('submit_type');

        if(!$sample_ids) return back();

        foreach ($sample_ids as $key => $value) {
            $sample = Sample::find($value);
            if($sample->batch_id != $batch->id) continue;

            $sample->spots = $spots_array[$key] ?? 5;
            $sample->labcomment = $request->input('labcomment');
            $sample->sample_received_by = $request->input('received_by');

            if($submit_type == "accepted"){
                $sample->receivedstatus = 1;
            }else if($submit_type == "rejected"){
                $sample->receivedstatus = 2;
                $sample->rejectedreason = $rejectedreason_array[$key] ?? null;
            }
            $sample->save();
        }
        // $batch->received_by = auth()->user()->id;
        $batch->received_by = $request->input('received_by');
        $batch->datereceived = $request->input('datereceived');
        $batch->save();
        Refresh::refresh_cache();
        session(['toast_message' => 'The selected samples have been ' . $submit_type]);

        if($submit_type == "accepted"){
            $work_samples = Misc::get_worksheet_samples(2);
            if($work_samples['count'] > 21) session(['toast_message' => 'The selected samples have been accepted.<br />You now have ' . $work_samples['count'] . ' samples that are eligible for testing.']);
        }

        $sample = Sample::where('batch_id', $batch->id)->whereNull('receivedstatus')->get()->first();
        if($sample) return back();
        return redirect('batch/site_approval');        
    }

    /**
     * Print the specified resource.
     *
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function individual(Batch $batch)
    {
        if(!$batch->dateindividualresultprinted){
            $batch->dateindividualresultprinted = date('Y-m-d');
            $batch->pre_update();
        }

        $data = Lookup::get_lookups();
        $samples = $batch->sample;
        $samples->load(['patient.mother', 'approver', 'batch.lab', 'batch.facility', 'batch.receiver', 'batch.creator']);
        $data['samples'] = $samples;

        return view('exports.mpdf_samples', $data)->with('pageTitle', 'Individual Batch');
    }

    public function individual_pdf(Batch $batch)
    {
        $filename = "individual_results_for_batch_" . $batch->id . ".pdf";

        $mpdf = new Mpdf();
        $data = Lookup::get_lookups();
        $samples = $batch->sample;
        $samples->load(['patient.mother', 'approver', 'batch.lab', 'batch.facility', 'batch.receiver', 'batch.creator']);
        $data['samples'] = $samples;
        $view_data = view('exports.mpdf_samples', $data)->render();
        $mpdf->WriteHTML($view_data);
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    }

    /**
     * Print the specified resource.
     *
     * @param  \App\Batch  $batch
     * @return \Illuminate\Http\Response
     */
    public function summary(Batch $batch)
    {
        if(!$batch->datebatchprinted){
            $batch->datebatchprinted = date('Y-m-d');
            $batch->printedby = auth()->user()->id;
            $batch->pre_update();
        }

        $filename = "summary_results_for_batch_" . $batch->id . ".pdf";

        // $summary_path = storage_path('app/public/batches/eid/summary-' . $batch->id . '.pdf');
        // if(!is_dir(storage_path('app/public/batches/eid'))) mkdir(storage_path('app/public/batches/eid/'), 0777, true);
        // if(file_exists($summary_path)) unlink($summary_path);

        $batch->load(['sample.patient.mother', 'facility', 'lab', 'receiver', 'creator']);
        $data = Lookup::get_lookups();
        $data['batches'] = [$batch];
        $mpdf = new Mpdf(['format' => 'A4-L']);
        $view_data = view('exports.mpdf_samples_summary', $data)->render();
        $mpdf->WriteHTML($view_data);
        // $mpdf->Output($summary_path, \Mpdf\Output\Destination::FILE);
        // return redirect("storage/batches/eid/summary-{$batch->id}.pdf");
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

        // return response()->download($summary_path);
        

        // $mpdf->Output('summary.pdf', \Mpdf\Output\Destination::DOWNLOAD);
        // $mpdf->Output('summary.pdf', \Mpdf\Output\Destination::INLINE);
    }

    public function summaries(Request $request)
    {
        $batch_ids = $request->input('batch_ids');
        if(!$batch_ids) return back();
        if($request->input('print_type') == "individual") return $this->individuals($batch_ids);
        if($request->input('print_type') == "envelope") return $this->envelopes($batch_ids);
        $batches = Batch::whereIn('id', $batch_ids)->with(['sample.patient.mother', 'facility', 'lab', 'receiver', 'creator'])->get();

        foreach ($batches as $key => $batch) {
            if(!$batch->datebatchprinted){
                $batch->datebatchprinted = date('Y-m-d');
                $batch->printedby = auth()->user()->id;
                $batch->pre_update();
            }
        }

        $data = Lookup::get_lookups();
        $data['batches'] = $batches;
        $mpdf = new Mpdf(['format' => 'A4-L']);
        $view_data = view('exports.mpdf_samples_summary', $data)->render();
        $mpdf->WriteHTML($view_data);
        $mpdf->Output('summary.pdf', \Mpdf\Output\Destination::DOWNLOAD);
        // $mpdf->Output('summary.pdf', \Mpdf\Output\Destination::INLINE);
        
        // $pdf = DOMPDF::loadView('exports.samples_summary', $data)->setPaper('a4', 'landscape');
        // return $pdf->stream('summary.pdf');
    }

    public function individuals($batch_ids)
    {
        $samples = Sample::whereIn('batch_id', $batch_ids)->with(['patient.mother', 'approver'])->orderBy('batch_id')->get();
        $samples->load(['batch.lab', 'batch.facility', 'batch.receiver', 'batch.creator']);
        $data = Lookup::get_lookups();
        $data['samples'] = $samples;

        Batch::whereIn('id', $batch_ids)->update(['dateindividualresultprinted' => date('Y-m-d')]);

        return view('exports.mpdf_samples', $data)->with('pageTitle', 'Individual Batch');
    }

    public function envelope(Batch $batch)
    {
        $batch->load(['facility.facility_contact', 'view_facility']);
        $data['batches'] = [$batch];
        return view('exports.envelopes', $data);
    }

    public function envelopes($batch_ids)
    {
        $batches = Batch::whereIn('id', $batch_ids)->with(['facility.facility_contact', 'view_facility'])->get();
        return view('exports.envelopes', ['batches' => $batches]);
    }

    public function email(Batch $batch)
    {
        // $facility = Facility::find($batch->facility_id);
        // if($facility->email != null || $facility->email != '')
        // {
        //     $mail_array = array('joelkith@gmail.com', 'tngugi@gmail.com', 'baksajoshua09@gmail.com');
        //     if(env('APP_ENV') == 'production') $mail_array = $facility->email_array;
        //     Mail::to($mail_array)->cc(['joel.kithinji@dataposit.co.ke', 'joshua.bakasa@dataposit.co.ke'])->send(new EidDispatch($batch));
        // }

        // if(!$batch->sent_email){
        //     $batch->sent_email = true;
        //     $batch->dateemailsent = date('Y-m-d');
        //     $batch->save();
        // }

        Misc::dispatch_batch($batch);

        session(['toast_message' => "The batch {$batch->id} has had its results sent to the facility."]);
        return back();
    }

    public function dispatch_report($date_start=NULL, $date_end=NULL, $facility_id=NULL, $subcounty_id=NULL, $partner_id=NULL)
    {
        $date_column = "batches.datedispatched";

        $samples = Sample::select(['samples.batch_id', 'facilitys.name as facility', 'districts.name as subcounty', 'patients.patient', 'samples.result', 'samples.receivedstatus', 'batches.datedispatched'])
            ->leftJoin('patients', 'patients.id', '=', 'samples.patient_id')
            ->leftJoin('batches', 'batches.id', '=', 'samples.batch_id')
            ->leftJoin('facilitys', 'facilitys.id', '=', 'batches.facility_id')
            ->leftJoin('districts', 'districts.id', '=', 'facilitys.district')
            // ->leftJoin('users', 'users.id', '=', 'batches.user_id')
            ->when($date_start, function($query) use ($date_column, $date_start, $date_end){
                if($date_end)
                {
                    return $query->whereDate($date_column, '>=', $date_start)
                    ->whereDate($date_column, '<=', $date_end);
                }
                return $query->whereDate($date_column, $date_start);
            })
            ->when($facility_id, function($query) use ($facility_id){
                return $query->where('batches.facility_id', $facility_id);
            })
            ->when($subcounty_id, function($query) use ($subcounty_id){
                return $query->where('facilitys.district', $subcounty_id);
            })
            ->when($partner_id, function($query) use ($partner_id){
                return $query->where('facilitys.partner', $partner_id);
            })
            ->where('batch_complete', 1)
            ->orderBy($date_column, 'desc')
            ->orderBy('batch_id', 'desc')
            ->get();

        $data = [];

        foreach ($samples as $key => $sample) {
            $data[$key]['#'] = $key+1;
            $data[$key]['Batch #'] = $sample->batch_id;
            $data[$key]['Facility'] = $sample->facility;
            $data[$key]['Sub County'] = $sample->subcounty;
            $data[$key]['Sample/Patient ID'] = $sample->{'patient'};
            $data[$key]['Test Outcome'] = $sample->result_name;
            $data[$key]['Date Dispatched'] = $sample->my_date_format('datedispatched');
            $data[$key]['Time Dispatched'] = '';
            $data[$key]['Dispatched By'] = '';
            $data[$key]['Initials'] = '';
            $data[$key]['Collected By'] = '';            
        }

        if(!$data) return null;

        $date_start = Lookup::my_date_format($date_start);
        $date_end = Lookup::my_date_format($date_end);

        $filename = "DISPATCH REPORT FOR EID RESULTS DISPATCHED BETWEEN {$date_start} AND {$date_end}";

        Excel::create($filename, function($excel) use($data) {
            $excel->sheet('Sheetname', function($sheet) use($data) {
                $sheet->fromArray($data);
            });
        })->download('xls');

    }


    public function search(Request $request)
    {
        $user = auth()->user();
        $string = "(user_id='{$user->id}' OR facility_id='{$user->facility_id}')";

        $search = $request->input('search');
        $batches = Batch::whereRaw("id like '" . $search . "%'")
            ->when(true, function($query) use ($user, $string){
                if($user->user_type_id == 5) return $query->whereRaw($string);
                return $query->where('lab_id', $user->lab_id);
            })
            ->paginate(10);

        $batches->setPath(url()->current());
        return $batches;
    }

    public function checknull($var)
    {
        return $var->first()->totals ?? 0;
    }

    public function batches_transformer(&$batches)
    {  
        $batches->setPath(url()->current());

        $batch_ids = $batches->pluck(['id'])->toArray();

        if($batch_ids){
            $subtotals = Misc::get_subtotals($batch_ids, false);
            $rejected = Misc::get_rejected($batch_ids, false);
            $date_modified = Misc::get_maxdatemodified($batch_ids, false);
            $date_tested = Misc::get_maxdatetested($batch_ids, false);
        }else{
            $subtotals = $rejected = $date_modified = $date_tested = false;
        }

        $batches->transform(function($batch, $key) use ($subtotals, $rejected, $date_modified, $date_tested){

            if(!$subtotals && !$rejected){
                $total = $rej = $result = $noresult = $pos + $neg + $redraw + $failed = 0;
            }
            else{
                $neg = $subtotals->where('batch_id', $batch->id)->where('result', 1)->first()->totals ?? 0;
                $pos = $subtotals->where('batch_id', $batch->id)->where('result', 2)->first()->totals ?? 0;
                $failed = $subtotals->where('batch_id', $batch->id)->where('result', 3)->first()->totals ?? 0;
                $redraw = $subtotals->where('batch_id', $batch->id)->where('result', 5)->first()->totals ?? 0;
                // $noresult = $subtotals->where('batch_id', $batch->id)->where('result', 0)->first()->totals ?? 0;
                $noresult = $subtotals->where('batch_id', $batch->id)->where('result', null)->first()->totals ?? 0;
                // $noresult += $n;

                $rej = $rejected->where('batch_id', $batch->id)->first()->totals ?? 0;
                $total = $neg + $pos + $failed + $redraw + $noresult + $rej;

                $result = $pos + $neg + $redraw + $failed;
            }

            $batch->date_modified = $date_modified->where('batch_id', $batch->id)->first()->mydate ?? '';
            $batch->date_tested = $date_tested->where('batch_id', $batch->id)->first()->mydate ?? '';

            $batch->creator = $batch->surname . ' ' . $batch->oname;
            $batch->datecreated = $batch->my_date_format('created_at');
            $batch->datereceived = $batch->my_date_format('datereceived');
            $batch->datedispatched = $batch->my_date_format('datedispatched');
            $batch->total = $total;
            $batch->rejected = $rej;
            $batch->result = $result;
            $batch->noresult = $noresult;

            $batch->pos = $pos;
            $batch->neg = $neg;
            $batch->redraw = $redraw;
            $batch->failed = $failed;

            $batch->status = $batch->batch_complete;
            $batch->approval = false;
            return $batch;
        });

        return $batches;
    }


}
