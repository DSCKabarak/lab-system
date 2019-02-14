<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDrSamplesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dr_samples', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('patient_id')->unsigned()->index();
            $table->integer('facility_id')->unsigned()->index();

            // 0 is for normal sample
            // 1 is negative control
            // 2 is positive control
            $table->tinyInteger('control')->unsigned()->nullable()->default(0);
            $table->integer('sanger_id')->unsigned()->index()->nullable()->default(0);

            $table->tinyInteger('prev_prophylaxis')->unsigned()->nullable();
            $table->tinyInteger('prophylaxis')->unsigned()->nullable();
            $table->tinyInteger('receivedstatus')->unsigned()->nullable(); 
            $table->tinyInteger('rejectedreason')->unsigned()->nullable();


            $table->tinyInteger('sample_type')->unsigned()->nullable();
            // Multiple
            $table->string('clinical_indications', 50)->nullable(); 


            $table->boolean('has_opportunistic_infections')->default(0);
            $table->string('opportunistic_infections', 100)->nullable();

            $table->boolean('has_tb')->default(0);
            $table->tinyInteger('tb_treatment_phase_id')->unsigned()->nullable();

            $table->boolean('has_arv_toxicity')->default(0);
            // Multiple
            $table->string('arv_toxicities', 50)->nullable();

            $table->string('cd4_result', 50)->nullable();

            // Adherence
            $table->boolean('has_missed_pills')->default(0);
            $table->smallInteger('missed_pills')->unsigned()->nullable();
            $table->boolean('has_missed_visits')->default(0);
            $table->smallInteger('missed_visits')->unsigned()->nullable();
            $table->boolean('has_missed_pills_because_missed_visits')->default(0);

            // Multiple and mixed
            $table->string('other_medications', 100)->nullable();


            $table->boolean('repeatt')->default(0);
            $table->tinyInteger('run')->default(1)->unsigned();

            // startartdate
            $table->date('date_prev_regimen')->nullable(); 
            $table->date('date_current_regimen')->nullable(); 

            $table->integer('extraction_worksheet_id')->nullable()->unsigned()->index();
            $table->integer('worksheet_id')->nullable()->unsigned()->index();

            $table->date('datecollected')->nullable();
            $table->date('datereceived')->nullable();
            $table->date('datetested')->nullable();
            $table->date('datedispatched')->nullable();

            $table->tinyInteger('dr_reason_id')->nullable()->unsigned()->index();

            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('received_by')->unsigned()->nullable();

            $table->boolean('passed_gel_documentation')->nullable();

            $table->tinyInteger('status_id')->nullable()->unsigned()->index();

            $table->boolean('qc_pass')->default(0)->nullable();

            $table->boolean('qc_stop_codon_pass')->default(0)->nullable();
            $table->boolean('qc_plate_contamination_pass')->default(0)->nullable();
            $table->boolean('qc_frameshift_codon_pass')->default(0)->nullable();

            $table->smallInteger('qc_distance_to_sample')->nullable()->unsigned();
            $table->smallInteger('qc_distance_from_sample')->nullable()->unsigned();
            $table->float('qc_distance_difference', 4, 3)->unsigned()->nullable();
            $table->string('qc_distance_strain_name', 50)->nullable();
            $table->string('qc_distance_compare_to_name', 50)->nullable();
            $table->string('qc_distance_sample_name', 20)->nullable();


            $table->boolean('has_errors')->default(0);
            $table->boolean('has_warnings')->default(0);
            $table->boolean('has_calls')->default(0);
            $table->boolean('has_genotypes')->default(0);

            // PendChromatogramManualIntervention
            $table->boolean('pending_manual_intervention')->default(0);
            $table->boolean('had_manual_intervention')->default(0);


            $table->text('assembled_sequence')->nullable(); 
            $table->string('chromatogram_url', 50)->nullable(); 
            $table->string('pdf_download_link', 100)->nullable(); 
            $table->string('exatype_version', 50)->nullable(); 
            $table->string('algorithm', 20)->nullable(); 
            

            $table->tinyInteger('synched')->default(0)->nullable();
            $table->date('datesynched')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dr_samples');
    }
}
