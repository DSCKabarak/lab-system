<?php

namespace App;

use App\BaseModel;

class Viralsample extends BaseModel
{
    // protected $dates = ['datecollected', 'datetested', 'datemodified', 'dateapproved', 'dateapproved2', 'dateinitiatedontreatment', 'datesynched'];


    public function tat($datedispatched)
    {
        return \App\Misc::working_days($this->datecollected, $datedispatched);
    }

    public function patient()
    {
    	return $this->belongsTo('App\Viralpatient', 'patient_id');
    }

    public function batch()
    {
        return $this->belongsTo('App\Viralbatch', 'batch_id');
    }

    public function worksheet()
    {
        return $this->belongsTo('App\Viralworksheet', 'worksheet_id');
    }


    // Parent sample
    public function parent()
    {
        return $this->belongsTo('App\Viralsample', 'parentid');
    }

    // Child samples
    public function child()
    {
        return $this->hasMany('App\Viralsample', 'parentid');
    }

    public function creator()
    {
        return $this->belongsTo('App\User', 'createdby');
    }

    public function canceller()
    {
        return $this->belongsTo('App\User', 'cancelledby');
    }

    public function reviewer()
    {
        return $this->belongsTo('App\User', 'reviewedby');
    }

    public function approver()
    {
        return $this->belongsTo('App\User', 'approvedby');
    }

    public function scopeRuns($query, $sample)
    {
        if($sample->parentid == 0){
            return $query->whereRaw("parentid = {$sample->id} or id = {$sample->id}")->orderBy('run', 'asc');
        }
        else{
            return $query->whereRaw("parentid = {$sample->parentid} or id = {$sample->parentid}")->orderBy('run', 'asc');
        }
    }



    public function last_test()
    {
        $sample = \App\Viralsample::where('patient_id', $this->patient_id)
                ->whereRaw("datetested=
                    (SELECT max(datetested) FROM viralsamples WHERE patient_id={$this->patient_id} AND repeatt=0 AND rcategory between 1 and 4 AND datetested < '{$this->datetested}')")
                ->get()->first();
        $this->recent = $sample;
    }

    public function prev_tests()
    {
        $s = $this;
        $samples = \App\Viralsample::where('patient_id', $this->patient_id)
                ->when(true, function($query) use ($s){
                    if($s->datetested) return $query->where('datetested', '<', $s->datetested);
                    return $query->where('datecollected', '<', $s->datecollected);
                })
                ->where('repeatt', 0)
                ->whereIn('rcategory', [1, 2, 3, 4])
                ->orderBy('id', 'desc')
                ->get();
        $this->previous_tests = $samples;
    }
    

    /**
     * Get the sample's coloured result name
     *
     * @return string
     */

    public function getColouredResultAttribute()
    {
        if(is_numeric($this->result)){
            if($this->result < 1000){
                return "<strong><div style='color: #00ff00;'>{$this->result} </div></strong>";
            }
            else{
                return "<strong><div style='color: #ff0000;'>{$this->result} </div></strong>";             
            }
        }
        else if($this->result == "< LDL copies/ml" || $this->result == "Target Not Detected"){
            return "<strong><div style='color: #00ff00;'>&lt; LDL copies/ml</div></strong>";
        }
        else{
            return "<strong><div style='color: #cccc00;'>{$this->result} </div></strong>";
        }
    }

    /**
     * Get the sample's Sample Type
     *
     * @return string
     */
    public function getSampleTypeOutputAttribute()
    {
        if($this->sampletype == 1) return "PLASMA";
        else if($this->sampletype == 2) return "EDTA";
        else if($this->sampletype == 3) return "DBS Capillary";
        else if($this->sampletype == 4) return "DBS Venous";
        return "";
    }

    /**
     * Get the sample's result comment
     *
     * @return string
     */
    public function getResultCommentAttribute()
    {
        $str = '';
        $result = $this->result;
        $interpretation = $this->interpretation;
        $lower_interpretation = strtolower($interpretation);
        // < ldl
        if(str_contains($interpretation, ['<'])){
            $str = "LDL:Lower Detectable Limit i.e. Below Detectable levels by machine ";
            if(str_contains($interpretation, ['839'])){
                $str .= "( Abbott DBS  &lt;839 copies/ml )";
            }
            else if(str_contains($interpretation, ['40'])){
                $str .= "( Abbott Plasma  &lt;40 copies/ml )";
            }
            else if(str_contains($interpretation, ['150'])){
                $str .= "( Abbott Plasma  &lt;150 copies/ml )";
            }
            else if(str_contains($interpretation, ['20'])){
                $str .= "( Roche Plasma  &lt;20 copies/ml )";
            }
            else if(str_contains($interpretation, ['30'])){
                $str .= "( Pantha Plasma  &lt;30 copies/ml )";
            }
            else{
                $n = preg_replace("/[^<0-9]/", "", $interpretation);
                $str .= "( &lt;{$n} copies/ml )";
            }
        }
        else if(str_contains($result, ['<']) && str_contains($lower_interpretation, ['not detected'])){
            $str = "No circulating virus ie. level of HIV in blood is below the threshold needed for detection by this test. Doesn’t mean client Is Negative";
        }
        else if($result == "Target Not Detected"){
            $str = "No circulating virus ie. level of HIV in blood is below the threshold needed for detection by this test. Doesn’t mean client Is Negative";
        }
        else if($result == "Collect New Sample" || $result == "Failed"){
            $str = "Sample failed during processing due to sample deterioration or equipment malfunction.  Redraw another sample and send to lab as soon as possible";
        }
        else{}
        return "<small>{$str}</small>";
    }


    
}
