<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseRequest;
use Config;
use App\Rules\BeforeOrEqual;

class ViralsampleRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if($this->input('facility_id') == 7148) return [];

        $base = Config::get('boilerplate.form_base'); 
        $specifics = Config::get('boilerplate.vl'); 
        $received = Config::get('boilerplate.lab_user'); 

        $rules = array_merge($base, $specifics);

        $user  = auth()->user();

        if($user->is_lab_user()) $rules = array_merge($rules, $received);

        $rules['dob'] = array_merge($rules['dob'], [new BeforeOrEqual($this->input('datecollected'), 'datecollected'), 'after_or_equal:-100years']);

        return $rules;
    }
}