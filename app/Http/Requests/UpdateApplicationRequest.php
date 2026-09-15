<?php

namespace App\Http\Requests;

class UpdateApplicationRequest extends StoreApplicationRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
