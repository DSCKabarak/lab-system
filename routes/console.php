<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('generate:dr-list', function(){
    $str = \App\MiscViral::generate_dr_list();
    $this->info($str);
})->describe('Generate a list of potential dr patients.');

Artisan::command('compute:eid-tat', function(){
    $my = new \App\Misc;
    $str = $my->compute_tat(\App\SampleView::class, \App\Sample::class);
    $str .= "Completed eid tat computation at " . date('d/m/Y h:i:s a', time()). "\n";
    $this->info($str);
})->describe('Compute Eid Tat.');

Artisan::command('compute:vl-tat', function(){
    $my = new \App\MiscViral;
    $str = $my->compute_tat(\App\ViralsampleView::class, \App\Viralsample::class);
    $str .= "Completed vl tat computation at " . date('d/m/Y h:i:s a', time()). "\n";
    $this->info($str);
})->describe('Compute Vl Tat.');

Artisan::command('compute:vl-stat {sample_id}', function($sample_id){
    $my = new \App\MiscViral;
    $str = $my->compute_tat_sample(\App\ViralsampleView::class, \App\Viralsample::class, $sample_id);
    $str .= "Completed vl at " . date('d/m/Y h:i:s a', time()). "\n";
    $this->info($str);
})->describe('Compute Vl Tat.');


Artisan::command('dispatch:results', function(){
    $str = \App\Common::dispatch_results('eid');
    $str = \App\Common::dispatch_results('vl');
    $this->info($str);
})->describe('Send emails for dispatched batches.');

Artisan::command('dispatch:mlab', function(){
    $str = \App\Misc::send_to_mlab();
    $str .= \App\MiscViral::send_to_mlab();
    $this->info($str);
})->describe('Post dispatched results to mlab.');


Artisan::command('input-complete', function(){
    $str = \App\Common::input_complete_batches('eid');
    $str = \App\Common::input_complete_batches('vl');
    $this->info($str);
})->describe('Mark batches as input completed.');


Artisan::command('batch-complete', function(){
    $str = \App\Common::check_batches('eid');
    $str = \App\Common::check_batches('vl');
    $this->info($str);
})->describe('Check if batch is ready for dispatch.');


Artisan::command('fix:noage', function(){
    $str = \App\Common::fix_no_age('eid');
    $str = \App\Common::fix_no_age('vl');
    $this->info($str);
})->describe('Fix no age.');


Artisan::command('delete:empty-batches', function(){
    \App\Misc::delete_empty_batches();
    \App\MiscViral::delete_empty_batches();
})->describe('Delete empty batches.');


Artisan::command('delete:pdfs', function(){
    $str = \App\Common::delete_folder(storage_path('app/batches'));
    $this->info($str);
})->describe('Delete pdfs from hard drive.');





Artisan::command('lablog', function(){
    $str = \App\Synch::labactivity('eid');
	$str = \App\Synch::labactivity('vl');
    $this->info($str);
})->describe('Send lablog data to national.');

// Artisan::command('synch:vl-patients', function(){
// 	$str = \App\Synch::synch_vl_patients();
//     $this->info($str);
// })->describe('Synch vl patients to the national database.');


Artisan::command('send:communication', function(){
    $str = \App\Common::send_communication();
    $this->info($str);
})->describe('Send any pending emails.');


Artisan::command('send:sms', function(){
    $str = \App\Misc::patient_sms();
    $str .= \App\MiscViral::patient_sms();
    $this->info($str);
})->describe('Send result sms.');


Artisan::command('send:weekly-activity', function(){
    $str = \App\Synch::send_weekly_activity();
    $this->info($str);
})->describe('Send out weekly activity sms alert.');


Artisan::command('send:weekly-backlog', function(){
    $str = \App\Synch::send_weekly_backlog();
    $this->info($str);
})->describe('Send out weekly backlog sms alert.');



Artisan::command('synch:patients', function(){
    // if($type == 'eid') $str = \App\Synch::synch_eid_patients();
    // else { $str = \App\Synch::synch_vl_patients(); }  
    $str = \App\Synch::synch_eid_patients();  
    $str .= \App\Synch::synch_vl_patients();  
    $this->info($str);
})->describe('Synch patients to the national database.');


Artisan::command('synch:batches', function(){
    $str = \App\Synch::synch_batches('eid');
	$str = \App\Synch::synch_batches('vl');
    $this->info($str);
})->describe('Synch batches to the national database.');


Artisan::command('synch:worksheets', function(){
    $str = \App\Synch::synch_worksheets('eid');
	$str = \App\Synch::synch_worksheets('vl');
    $this->info($str);
})->describe('Synch worksheets to the national database.');


Artisan::command('synch:updates', function(){
    $str = \App\Synch::synch_updates('eid');
    $str = \App\Synch::synch_updates('vl');
    $this->info($str);
})->describe('Synch updates to the national database.');


Artisan::command('synch:deletes', function(){
    $str = \App\Synch::synch_deletes('eid');
	$str = \App\Synch::synch_deletes('vl');
    $this->info($str);
})->describe('Synch deletes to the national database.');





Artisan::command('copy:eid', function(){
	$str = \App\Copier::copy_eid();
    $this->info($str);
})->describe('Copy eid data from old database to new database.');

Artisan::command('copy:vl', function(){
	$str = \App\Copier::copy_vl();
    $this->info($str);
})->describe('Copy vl data from old database to new database.');


Artisan::command('copy:worksheet', function(){
	$str = \App\Copier::copy_worksheet();
    $this->info($str);
})->describe('Copy worksheet data from old database to new database.');


Artisan::command('copy:worklist', function(){
    $str = \App\Copier::copy_worklist();
    $this->info($str);
})->describe('Copy worklist data from old database to new database.');

Artisan::command('copy:deliveries', function(){
    $str = \App\Copier::copy_deliveries();
    $this->info($str);
})->describe('Copy deliveries data from old database to new database.');

Artisan::command('copy:facility-contacts', function(){
    $str = \App\Copier::copy_facility_contacts();
    $this->info($str);
})->describe('Copy facility contacts from old database to new database.');

Artisan::command('copy:facility-missing', function(){
    $str = \App\Copier::copy_missing_facilities();
    $this->info($str);
})->describe('Copy missing facilities from old database to new database.');

Artisan::command('copy:cd4', function(){
    $str = \App\Copier::cd4();
    $this->info($str);
})->describe('Copy cd4 data from old database to new database.');



Artisan::command('match:eid-patients', function(){
    $str = \App\Synch::match_eid_patients();
    $this->info($str);
})->describe('Copy facility contacts from old database to new database.');



Artisan::command('match:patients {type}', function($type){
    if($type == 'eid') $str = \App\Synch::match_eid_patients();
    else { $str = \App\Synch::match_vl_patients(); }    
    $this->info($str);
})->describe('Match patients with records on the national database.');


Artisan::command('match:batches {type}', function($type){
    $str = \App\Synch::match_batches($type);
    $this->info($str);
})->describe('Match batches with records on the national database.');


Artisan::command('match:poc', function(){
    $str = \App\Copier::match_eid_poc_batches();
    $str = \App\Copier::match_vl_poc_batches();
    $this->info($str);
})->describe('Match POC records.');

Artisan::command('test:email', function(){
	$str = \App\Common::test_email();
    $this->info($str);
})->describe('Send test email.');

Artisan::command('test:connection', function(){
    $str = \App\Synch::test_connection();
    $this->info($str);
})->describe('Check connection to lab-2.test.nascop.org.');


