<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('high_priority')->default(false);
            $table->boolean('input_complete')->default(false);
            $table->boolean('batch_full')->default(false);

            // 0 is default i.e. new
            // 1 is dispatched
            // 2 is staging i.e. all samples are ready, batch awaiting dispatch
            $table->tinyInteger('batch_complete')->default(0);
            $table->tinyInteger('site_entry')->unsigned()->nullable();

            $table->boolean('sent_email')->default(false);

            $table->integer('printedby')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->index();
            $table->integer('received_by')->unsigned()->nullable();

            $table->tinyInteger('lab_id')->unsigned()->index();
            $table->integer('facility_id')->unsigned()->index();

            $table->date('datedispatchedfromfacility')->nullable();
            $table->date('datereceived')->nullable()->index();
            $table->date('datedispatched')->nullable()->index();
            $table->date('dateindividualresultprinted')->nullable();

            $table->boolean('synched')->default(false);
            $table->date('datesynched')->nullable();
            // $table->date('created_at')->nullable();
            // $table->date('updated_at')->nullable();
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
        Schema::dropIfExists('batches');
    }
}