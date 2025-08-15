<?php

namespace BDCGenerator\DocumentGenerator\Models;

use Illuminate\Database\Eloquent\Model;

class DocTemplate extends Model
{
    protected $table = 'doc_templates';
    protected $fillable = ['template_key', 'descriptor', 'is_active', 'version'];
    protected $casts = ['descriptor' => 'array', 'is_active' => 'bool'];
    public $timestamps = true;
}
