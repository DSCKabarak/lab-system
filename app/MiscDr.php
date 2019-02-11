<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use DB;

use App\Common;
use App\DrSample;
use App\DrSampleView;

use App\DrWorksheetWarning;
use App\DrWarning;

use App\DrCall;
use App\DrCallDrug;

use App\DrGenotype;
use App\DrResidue;

class MiscDr extends Common
{

	public static $hyrax_url = 'https://sanger20181106v2-sanger.hyraxbio.co.za';
	public static $ui_url = 'http://sangelamerkel.exatype.co.za';

    public static $call_array = [
        'LC' => [
            'resistance' => 'Low Coverage',
            'resistance_colour' => "#595959",
            'cells' => [],
        ],
        'R' => [
            'resistance' => 'Resistant',
            'resistance_colour' => "#ff0000",
            'cells' => [],
        ],
        'I' => [
            'resistance' => 'Intermediate Resistance',
            'resistance_colour' => "#ff9900",
            'cells' => [],
        ],
        'S' => [
            'resistance' => 'Susceptible',
            'resistance_colour' => "#00ff00",
            'cells' => [],
        ],
    ];

    public static function dump_log($postData, $encode_it=true)
    {
    	if(!is_dir(storage_path('app/logs/'))) mkdir(storage_path('app/logs/'), 0777);

		if($encode_it) $postData = json_encode($postData);
		
		$file = fopen(storage_path('app/logs/' . 'dr_logs2' .'.txt'), "a");
		if(fwrite($file, $postData) === FALSE) fwrite("Error: no data written");
		fwrite($file, "\r\n");
		fclose($file);
    }

	public static function get_hyrax_key()
	{
		if(Cache::store('file')->has('dr_api_token')){}
		else{
			self::login();
		}
		return Cache::store('file')->get('dr_api_token');
	}

	public static function login()
	{
		Cache::store('file')->forget('dr_api_token');
		$client = new Client(['base_uri' => self::$hyrax_url]);

		$response = $client->request('POST', 'sanger/authorisations', [
			'headers' => [
				// 'Accept' => 'application/json',
			],
			'json' => [
				'data' => [
					'type' => 'authorisations',
					'attributes' => [
						'email' => env('DR_USERNAME'),
						'password' => env('DR_PASSWORD'),
					],
				],
			],
		]);

		$body = json_decode($response->getBody());

		if($response->getStatusCode() < 400)
		{
			$key = $body->data->attributes->api_key ?? null;

			if(!$key) dd($body);

			Cache::store('file')->put('dr_api_token', $key, 60);

			// echo $key;
			return;
		}
		die();
	}


	public static function create_plate($worksheet)
	{
		$client = new Client(['base_uri' => self::$hyrax_url]);

		$files = self::get_worksheet_files($worksheet);

		$sample_data = $files['sample_data'];
		$errors = $files['errors'];

		if($errors){
			return $errors;
		}

		$postData = [
				'data' => [
					'type' => 'plate_create',
					'attributes' => [
						'plate_name' => "{$worksheet->id}",
					],
				],
				'included' => $sample_data,
			];

		// self::dump_log($postData);

		// die();

		$response = $client->request('POST', 'sanger/plate', [
            'http_errors' => false,
            // 'debug' => true,
			'headers' => [
				// 'Accept' => 'application/json',
				// 'x-hyrax-daemon-apikey' => self::get_hyrax_key(),
				'X-Hyrax-Apikey' => self::get_hyrax_key(),
			],
			'json' => $postData,
		]);

		$body = json_decode($response->getBody());

		if($response->getStatusCode() < 400)
		{
			$worksheet->plate_id = $body->data->id;
			$worksheet->time_sent_to_sanger = date('Y-m-d H:i:s');
			$worksheet->status_id = 5;
			$worksheet->save();

			foreach ($body->data->attributes->samples as $key => $value) {
				$sample = DrSample::find($value->sample_name);
				$sample->sanger_id = $value->id;
				$sample->save();
			}
		}

		echo "\n The status code is " . $response->getStatusCode() . "\n";

		// dd($body);
	}


	public static function get_worksheet_files($worksheet)
	{
		$path = storage_path('app/public/results/dr/' . $worksheet->id . '/');

		$samples = $worksheet->sample;
		// $samples->load(['result']);

		$primers = ['F1', 'F2', 'F3', 'R1', 'R2', 'R3'];

		$sample_data = [];
		$print_data = [];
		$errors = [];

		foreach ($samples as $key => $sample) {

			// if($key == 4) break;

			// if($key != 4) continue;

			$s = [
				'type' => 'sample_create',
				'attributes' => [
					'sample_name' => "{$sample->mid}",
					'pathogen' => 'hiv',
					'assay' => 'cdc-hiv',
					'enforce_recall' => false,
					'sample_type' => 'data',
				],
			];

			if($sample->control == 1) $s['attributes']['sample_type'] = 'negative';
			if($sample->control == 2) $s['attributes']['sample_type'] = 'positive';

			$abs = [];
			$abs2 = [];

			foreach ($primers as $primer) {
				$ab = self::find_ab_file($path, $sample, $primer);
				// if($ab) $abs[] = $ab;
				if($ab){
					$abs[] = $ab;
					// $abs2[] = ['file_name' => $ab['file_name']];
				}
				else{
					$errors[] = "Sample {$sample->id} Primer {$primer} could not be found.";
				}
			}
			if(!$abs) continue;
			$s['attributes']['ab1s'] = $abs;
			$sample_data[] = $s;

			// $s['attributes']['ab1s'] = $abs2;
			// $print_data[] = $s;
		}
		// self::dump_log($print_data);
		// die();
		return ['sample_data' => $sample_data, 'errors' => $errors];
	}

	public static function find_ab_file($path, $sample, $primer)
	{
		$files = scandir($path);
		if(!$files) return null;

		foreach ($files as $file) {
			if($file == '.' || $file == '..') continue;

			$new_path = $path . '/' . $file;
			if(is_dir($new_path)){
				$a = self::find_ab_file($new_path, $sample, $primer);

				if(!$a) continue;
				return $a;
			}
			else{
				// if(starts_with($file, $sample->mid . $primer)){
				if(starts_with($file, $sample->mid . '-') && str_contains($file, $primer))
				{
					$a = [
						'file_name' => $file,
						'data' => base64_encode(file_get_contents($new_path)),
					];
					return $a;
				}
				continue;
			}
		}
		return false;
	}

	public static function get_plate_result($worksheet)
	{
		$client = new Client(['base_uri' => self::$hyrax_url]);

		$response = $client->request('GET', "sanger/plate/result/{$worksheet->plate_id}", [
			'headers' => [
				// 'Accept' => 'application/json',
				'X-Hyrax-Apikey' => self::get_hyrax_key(),
			],
		]);

		$body = json_decode($response->getBody());

		if($response->getStatusCode() == 200)
		{
			$w = $body->data->attributes;
			$worksheet->sanger_status_id = self::get_worksheet_status($w->status);
			$worksheet->plate_controls_pass = $w->plate_controls_pass;
			$worksheet->qc_run = $w->plate_qc_run;
			$worksheet->qc_pass = $w->plate_qc;

			if($worksheet->sanger_status_id == 4) return null;

			if($worksheet->sanger_status_id != 5){

				if($w->errors){
					foreach ($w->errors as $error) {
						$e = DrWorksheetWarning::firstOrCreate([
							'worksheet_id' => $worksheet->id,
							'warning_id' => self::get_sample_warning($error->title),
							'system' => $error->system ?? '',
							'detail' => $error->detail ?? '',
						]);
					}
				}

				if($w->warnings){
					foreach ($w->warnings as $error) {
						$e = DrWorksheetWarning::firstOrCreate([
							'worksheet_id' => $worksheet->id,
							'warning_id' => self::get_sample_warning($error->title),
							'system' => $error->system ?? '',
							'detail' => $error->detail ?? '',
						]);
					}
				}
			}

			$worksheet->status_id = 6;
			$worksheet->save();

			foreach ($body->included as $key => $value) {

				$sample = DrSample::where(['sanger_id' => $value->attributes->id])->first();

				if($sample){

					if($worksheet->sanger_status_id == 5 && !$worksheet->plate_controls_pass && !$sample->control) continue;

					$s = $value->attributes;
					$sample->status_id = self::get_sample_status($s->status_id);	

					if($sample->status_id == 3)	$sample->qc_pass = 0;			

					if($s->sample_qc_pass){
						$sample->qc_pass = $s->sample_qc_pass;

						$sample->qc_stop_codon_pass = $s->sample_qc->stop_codon_pass;
						$sample->qc_plate_contamination_pass = $s->sample_qc->plate_contamination_pass;
						$sample->qc_frameshift_codon_pass = $s->sample_qc->frameshift_codon_pass;
					}

					if($s->sample_qc_distance){
						$sample->qc_distance_to_sample = $s->sample_qc_distance[0]->to_sample_id;
						$sample->qc_distance_from_sample = $s->sample_qc_distance[0]->from_sample_id;
						$sample->qc_distance_difference = $s->sample_qc_distance[0]->difference;
						$sample->qc_distance_strain_name = $s->sample_qc_distance[0]->strain_name;
						$sample->qc_distance_compare_to_name = $s->sample_qc_distance[0]->compare_to_name;
						$sample->qc_distance_sample_name = $s->sample_qc_distance[0]->sample_name;
					}

					if($s->errors){
						$sample->has_errors = true;

						foreach ($s->errors as $error) {
							$e = DrWarning::firstOrCreate([
								'sample_id' => $sample->id,
								'warning_id' => self::get_sample_warning($error->title),
								'system' => $error->system,
								'detail' => $error->detail,
							]);
						}
					}

					if($s->warnings){
						$sample->has_warnings = true;

						foreach ($s->warnings as $error) {
							$e = DrWarning::firstOrCreate([
								'sample_id' => $sample->id,
								'warning_id' => self::get_sample_warning($error->title),
								'system' => $error->system,
								'detail' => $error->detail,
							]);
						}
					}

					if($s->calls){
						$sample->has_calls = true;

						foreach ($s->calls as $call) {
							// $c = DrCall::where(['sample_id' => $sample->id, 'drug_class' => $call->drug_class])->first();
							// if(!$c) $c = new DrCall;

							// $c->fill([
							// 	'sample_id' => $sample->id,
							// 	'drug_class' => $call->drug_class,
							// 	'other_mutations' => $call->other_mutations,
							// 	'major_mutations' => $call->major_mutations,
							// ]);

							// $c->save();

							$c = DrCall::firstOrCreate([
								'sample_id' => $sample->id,
								'drug_class' => $call->drug_class,
								'drug_class_id' => self::get_drug_class($call->drug_class),
								'mutations' => self::escape_null($call->mutations),
								// 'other_mutations' => self::escape_null($call->other_mutations),
								// 'major_mutations' => self::escape_null($call->major_mutations),
							]);

							foreach ($call->drugs as $drug) {
								$d = DrCallDrug::firstOrCreate([
									'call_id' => $c->id,
									'short_name' => $drug->short_name,
									'short_name_id' => self::get_short_name_id($drug->short_name),
									'call' => $drug->call,
								]);
							}
						}
					}

					if($s->genotype){
						$sample->has_genotypes = true;

						foreach ($s->genotype as $genotype) {
							$g = DrGenotype::firstOrCreate([
								'sample_id' => $sample->id,
								'locus' => $genotype->locus,
							]);

							foreach ($genotype->residues as $residue) {
								$r = DrResidue::firstOrCreate([
									'genotype_id' => $g->id,
									'residue' => $residue->residues[0] ?? null,
									'position' => $residue->position,
								]);
							}
						}
					}

					if($s->pending_action == "PendChromatogramManualIntervention"){
						$sample->pending_manual_intervention = true;
					}

					if(!$s->pending_action && $sample->pending_manual_intervention){
						$sample->pending_manual_intervention = false;
						$sample->had_manual_intervention = true;
					}				

					$sample->assembled_sequence = $s->assembled_sequence;
					$sample->chromatogram_url = $s->chromatogram_url;
					$sample->exatype_version = $s->exatype_version;
					$sample->algorithm = $s->algorithm;
					$sample->save();
				}
			}
		}

		// dd($body);
	}

	public static function get_worksheet_status($id)
	{
		return DB::table('dr_plate_statuses')->where(['name' => $id])->first()->id;
	}

	public static function get_sample_status($id)
	{
		return DB::table('dr_sample_statuses')->where(['other_id' => $id])->first()->id;
	}

	public static function get_sample_warning($id)
	{
		return DB::table('dr_warning_codes')->where(['name' => $id])->first()->id;
	}

	public static function get_drug_class($id)
	{
		return DB::table('regimen_classes')->where(['drug_class' => $id])->first()->drug_class_id ?? null;
	}

	public static function get_short_name_id($id)
	{
		return DB::table('regimen_classes')->where(['short_name' => $id])->first()->id ?? null;
	}

	public static function escape_null($var)
	{
		if($var) return $var;
		return null;
	}

	public static function set_drug_classes()
	{
		ini_set('memory_limit', '-1');

		$calls = DrCall::all();

		foreach ($calls as $key => $value) {
			$value->drug_class_id = self::get_drug_class($value->drug_class);
			$value->save();
		}

		$calls = DrCallDrug::all();

		foreach ($calls as $key => $value) {
			$value->short_name_id = self::get_short_name_id($value->short_name);
			$value->save();
		}
	}


	public static function get_extraction_worksheet_samples($limit=48)
	{
		$samples = DrSampleView::whereNull('worksheet_id')
		->whereNull('extraction_worksheet_id')
		->where('datereceived', '>', date('Y-m-d', strtotime('-1 year')))
		->where(['receivedstatus' => 1, 'control' => 0])
		->orderBy('datereceived', 'asc')
		->orderBy('id', 'asc')
		->limit($limit)
		->get();

		if($samples->count() == $limit){
			return ['samples' => $samples, 'create' => true, 'limit' => $limit];
		}
		return ['samples' => $samples, 'create' => false];
	}

	public static function get_worksheet_samples($extraction_worksheet_id)
	{
		$samples = DrSampleView::whereNull('worksheet_id')
		->where(['passed_gel_documentation' => true, 'extraction_worksheet_id' => $extraction_worksheet_id])
		->orderBy('control', 'desc')
		->orderBy('id', 'asc')
		->limit(16)
		->get();

		if($samples->count() > 0){
			return ['samples' => $samples, 'create' => true, 'extraction_worksheet_id' => $extraction_worksheet_id];
		}
		return ['create' => false, 'extraction_worksheet_id' => $extraction_worksheet_id];
	}


	public static function generate_samples()
	{
		$potential_patients = \App\DrPatient::where('status_id', 1)->limit(150)->get();

		foreach ($potential_patients as $patient) {
	        $data = $patient->only(['patient_id', 'dr_reason_id']);
	        $data['user_id'] = 0;
	        $data['receivedstatus'] = 1;
	        $data['datecollected'] = date('Y-m-d', strtotime('-2 days'));
	        $data['datereceived'] = date('Y-m-d');
	        // $sample = DrSample::create($data);
	        $sample = new DrSample;
	        $sample->fill($data);
	        $facility = $sample->patient->facility;
	        $sample->facility_id = $facility->id;
	        $sample->save();      

	        $patient->status_id=2;
	        $patient->save();
		}
	}


	public static function regimens()
	{
		$calls = \App\DrCallView::all();

		foreach ($calls as $key => $value) {
			$reg = DB::table('regimen_classes')->where(['drug_class' => $value->drug_class, 'short_name' => $value->short_name])->first();

			if(!$reg){
				DB::table('regimen_classes')->insert(['drug_class' => $value->drug_class, 'short_name' => $value->short_name]);
			}
		}
	}


	public static function seed()
	{		
    	\App\DrExtractionWorksheet::create(['lab_id' => env('APP_LAB'), 'createdby' => 1, 'date_gel_documentation' => date('Y-m-d')]);

    	\App\DrWorksheet::create(['extraction_worksheet_id' => 1]);

    	DB::table('dr_samples')->insert([
    		['id' => 1, 'control' => 1, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 2, 'control' => 2, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    	]);

    	DB::table('dr_samples')->insert([
    		['id' => 6, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 10, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 14, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 17, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 20, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 22, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 99, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 2009695759, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 2012693909, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 2012693911, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 2012693943, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 3005052934, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    		['id' => 3005052959, 'patient_id' => 1, 'worksheet_id' => 1, 'extraction_worksheet_id' => 1],
    	]);
	}


}
