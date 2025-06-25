<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class SampleContactsExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'phone_number',
            'profile_image',
        ];
    }
} 