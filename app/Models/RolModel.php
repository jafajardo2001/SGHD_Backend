<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolModel extends Model
{
    use HasFactory;
    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_actualizacion';
    protected $fillable = [
        'descripcion',
        'ip_creacion',
        'ip_actualizacion',
        'id_usuario_creador',
        'id_usuario_actualizo',
        'fecha_creacion',
        'fecha_actualizacion',
        'estado'
    ];


}
