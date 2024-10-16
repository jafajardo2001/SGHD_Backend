<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TituloAcademicoModel;
use App\Models\UsuarioModel;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Request as request_ip;
use Illuminate\Support\Facades\Log;

class TituloAcademicoController extends Controller
{

    public function getTituloAcademico(Request $request)
    {
        try{
            log::info("Peticion entrante " . __FILE__ ." -> ". __FUNCTION__ . " ip " . request_ip::ip());
            return Response()->json([
                "ok" => true,
                "data" => TituloAcademicoModel::when(isset($request->estado),function ($query) use($request){
                    if(is_array($request->estado)){
                        return $query->whereIn("estado",$request->estado);
                    }else{
                        return $query->where("estado","A");
                    }
                })->get(),
                "mensaje" => "Operacion realizada con exito"
            ],202);
        }catch(Exception $e){
            log::error(__FILE__ . __FUNCTION__ . " MENSAJE => " . $e->getMessage());
            return Response()->json([
                "ok" => false,
                "mensaje" => "Error interno en el servidor"
            ],505);
        }
    }

    public function storeTituloAcademico(Request $request)
{
    try {
        log::info("Peticion entrante " . __FILE__ ." -> ". __FUNCTION__ . " ip " . request_ip::ip());
        
        // Validar que el campo 'descripcion' esté presente
        if (!$request->input('descripcion')) {
            log::alert(__FILE__ . " -> " . __FUNCTION__ . " el parametro descripcion es obligatorio");
            return response()->json([
                "ok" => false,
                "mensaje" => "Hace falta el parametro descripcion"
            ], 404);
        }

        // Verificar si el título académico ya existe
        $tituloExistente = TituloAcademicoModel::where('descripcion', $request->input('descripcion'))->first();

        if ($tituloExistente) {
            // Si el título existe y está inactivo, actualizar su estado a "A"
            if ($tituloExistente->estado === 'E') {
                $tituloExistente->estado = 'A'; // Cambia el estado a Activo
                $tituloExistente->ip_actualizacion = request_ip::ip(); // Actualiza la IP
                $tituloExistente->id_usuario_actualizo = auth()->id() ?? 1; // Actualiza el usuario que hace el cambio
                $tituloExistente->fecha_actualizacion = Carbon::now(); // Actualiza la fecha
                $tituloExistente->save(); // Guarda los cambios

                return response()->json([
                    "ok" => true,
                    "mensaje" => "Título académico activado con éxito."
                ], 200);
            } else {
                // Si ya está activo, retornamos un mensaje de error
                return response()->json([
                    "ok" => false,
                    "mensaje" => "El título académico ya existe."
                ], 400);
            }
        }

        // Crear un nuevo título académico y asignar valores
        $modelo = new TituloAcademicoModel();
        $modelo->descripcion = $request->input('descripcion');
        $modelo->ip_creacion = request_ip::ip();
        $modelo->id_usuario_creador = auth()->id() ?? 1;
        $modelo->fecha_creacion = Carbon::now();
        $modelo->id_usuario_actualizo = auth()->id() ?? 1;
        $modelo->ip_actualizacion = request_ip::ip();
        $modelo->estado = 'A'; // Establece el estado a Activo
        $modelo->save();

        return response()->json([
            "ok" => true,
            "message" => "titulo academico creado con éxito."
        ], 200);

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje : " . $e->getMessage());
        Log::error("Linea : " . $e->getLine());
        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor."
        ], 500);
    }
}



public function updateTituloAcademico(Request $request, $id)
{
    try {
        log::info("Peticion entrante " . __FILE__ ." -> ". __FUNCTION__ . " ip " . request_ip::ip());
        
        // Buscar el modelo por ID
        $modelo = TituloAcademicoModel::find($id);
        
        if (!$modelo) {
            return response()->json([   
                "ok" => false,
                "mensaje" => " el registro no existe"
            ], 404);
        }

        // Obtener la nueva descripción
        $descripcion = $request->input('descripcion');

        // Validar que el nuevo título no exista
        if ($descripcion) {
            $tituloExistente = TituloAcademicoModel::where('descripcion', $descripcion)
                ->where('id_titulo_academico', '!=', $id) // Asegurarse de que no se compare con el mismo registro
                ->first();

            if ($tituloExistente) {
                return response()->json([
                    "ok" => false,
                    "message" => "el título académico ya existe."
                ], 400);
            }
        }

        // Actualizar campos
        $campos_actualizar = [
            "id_usuario_actualizo" => auth()->id() ?? 1,
            "fecha_actualizacion" => Carbon::now(),
        ];

        if ($descripcion) {
            $campos_actualizar["descripcion"] = ucfirst(trim($descripcion)); // Asegura que el título se almacene correctamente
        }

        // Realizar la actualización
        $modelo->update($campos_actualizar);

    return response()->json([
            "ok" => true,
            "message" => "titulo academico actualizado con éxito."
        ], 200);

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje : " . $e->getMessage());
        Log::error("Linea : " . $e->getLine());
        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor."
        ], 500);
    }
}


    public function deleteTituloAcademico(Request $request, $id)
{
    try {
        // Buscar el título académico por el id proporcionado
        $titulo = TituloAcademicoModel::find($id);
        
        if (!$titulo) {
            return response()->json([
                "ok" => false,
                "mensaje" => "El título académico no existe con el id $id"
            ], 404);
        }

        // Buscar los usuarios que tienen este id_titulo_academico asignado
        $usuariosConTitulo = UsuarioModel::where('id_titulo_academico', $id)->get();

        if ($usuariosConTitulo->isNotEmpty()) {
            // Quitar el id_titulo_academico de todos los usuarios que lo tienen asignado
            UsuarioModel::where('id_titulo_academico', $id)
                ->update([
                    'id_titulo_academico' => 0, // Desvincular el título académico
                    'id_usuario_actualizo' => auth()->id() ?? 1, // ID del usuario que realiza la actualización
                    'ip_actualizacion' => $request->ip(), // IP del usuario
                    'fecha_actualizacion' => now(), // Fecha y hora actual
                ]);
        }

        // Marcar el título académico como eliminado (cambiando su estado)
        $titulo->update([
            "estado" => "E", // Estado para indicar que está eliminado
            "id_usuario_actualizo" => auth()->id() ?? 1, // ID del usuario que realiza la actualización
            "ip_actualizo" => $request->ip(), // IP del usuario
            "fecha_actualizacion" => now(), // Fecha y hora actual
        ]);

        return response()->json([
            "ok" => true,
            "message" => "Título académico eliminado con éxito y usuarios actualizados"
        ], 200);
    } catch (Exception $e) {
        // Manejar cualquier excepción y registrar el error
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje: " . $e->getMessage());
        Log::error("Línea: " . $e->getLine());

        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor"
        ], 500);
    }
}

    
}
