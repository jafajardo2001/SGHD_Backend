<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TituloAcademicoModel extends Model
{
    use HasFactory;
    protected $table = 'titulo_academico';
    protected $primaryKey = 'id_titulo_academico';
    
    const CREATED_AT = 'fecha_creacion';
    const UPDATE_AT = 'fecha_actualizacion';

    protected $fillable = [
        'descripcion','codigo'
    ];
    
    
}
