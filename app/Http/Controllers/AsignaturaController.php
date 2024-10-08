<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Http\Responses\TypeResponse;
use App\Models\AsignaturaModel;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

class AsignaturaController extends Controller
{
    public function storeAsignatura(Request $request)
{
    try {
        // Validar que los campos 'descripcion' y 'nivel' estén presentes
        $request->validate([
            'descripcion' => 'required|string|max:255',
            'id_nivel' => 'required|integer'
        ]);

        // Verificar si ya existe una asignatura activa con la misma descripción y nivel
        $asignaturaExistente = AsignaturaModel::where('descripcion', $request->descripcion)
            ->where('id_nivel', $request->id_nivel)
            ->where('estado', 'A') // Solo buscar materias activas
            ->first();

        if ($asignaturaExistente) {
            return response()->json([
                "ok" => false,
                "msg_error" => "Ya existe una materia activa con la descripción '" . $request->descripcion . "' para el nivel " . $request->id_nivel
            ], 400);
        }

        // Verificar si existe una asignatura eliminada con la misma descripción y nivel
        $asignaturaEliminada = AsignaturaModel::where('descripcion', $request->descripcion)
            ->where('id_nivel', $request->id_nivel)
            ->where('estado', 'E') // Solo buscar materias eliminadas
            ->first();

        if ($asignaturaEliminada) {
            // Si la asignatura existe y está eliminada
            $asignaturaEliminada->estado = 'A'; // Cambiar estado a activo
            $asignaturaEliminada->ip_actualizacion = $request->ip();
            $asignaturaEliminada->id_usuario_actualizo = auth()->id() ?? 1;
            $asignaturaEliminada->save();

            return response()->json([
                "ok" => true,
                "message" => "Materia '" . $asignaturaEliminada->descripcion . "' reactivada con éxito"
            ], 200);
        }

        // Crear una nueva asignatura si no existe
        $modelo = new AsignaturaModel();
        $modelo->descripcion = $request->descripcion; // Guardar la descripción
        $modelo->id_nivel = $request->id_nivel; // Asegúrate de que este valor no sea nulo
        $modelo->ip_creacion = $request->ip();
        $modelo->ip_actualizacion = $request->ip();
        $modelo->id_usuario_creador = auth()->id() ?? 1;
        $modelo->id_usuario_actualizo = auth()->id() ?? 1;
        $modelo->estado = "A";
        $modelo->save();

        return response()->json([
            "ok" => true,
            "message" => "Materia '" . $modelo->descripcion . "' creada con éxito"
        ], 200);

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje: " . $e->getMessage());
        Log::error("Línea: " . $e->getLine());

        return response()->json([
            "ok" => false,
            "msg_error" => "Error interno en el servidor"
        ], 500);
    }
}







    public function deleteAsignatura(Request $request, $id)
{
    try {
        $asignatura = AsignaturaModel::find($id);

        if (!$asignatura) {
            return response()->json([
                "ok" => false,
                "message" => "La asignatura no existe con el id $id"
            ], 400);
        }

        $result = $asignatura->update([
            "estado" => "E",
            "id_usuario_creador" => auth()->id() ?? 1,
            "ip_actualizacion" => $request->ip(),
            "fecha_actualizacion" => now(),  // Estado cambiado a "E" para marcarlo como desactivado
        ]);

        if ($result) {
            return response()->json([
                "ok" => true,
                "message" => "materia eliminada con éxito"
            ], 200);
        } else {
            return response()->json([
                "ok" => false,
                "message" => "No se pudo eliminar la materia"
            ], 400);
        }

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje: " . $e->getMessage());
        Log::error("Línea: " . $e->getLine());

        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor"
        ], 500);
    }
}




public function updateAsignatura(Request $request, $id)
{
    try {
        $asignatura = AsignaturaModel::find($id);
        if (!$asignatura) {
            return response()->json([
                "ok" => false,
                "message" => "El registro no existe con el id $id"
            ], 400);
        }

        // Verificar si la nueva descripción y nivel ya existen en otra asignatura activa
        $asignaturaExistente = AsignaturaModel::where('descripcion', ucfirst(trim($request->descripcion)))
            ->where('id_nivel', $request->id_nivel) // Comprobar si el nivel es el mismo
            ->where('id_materia', '!=', $id) // Excluir la asignatura actual de la búsqueda
            ->where('estado', 'A') // Considerar solo asignaturas activas
            ->first();

        if ($asignaturaExistente) {
            return response()->json([
                "ok" => false,
                "message" => "Ya existe una asignatura activa con la misma descripción y nivel."
            ], 400);
        }

        // Actualizar la asignatura
        $asignatura->update([
            "descripcion" => isset($request->descripcion) ? ucfirst(trim($request->descripcion)) : $asignatura->descripcion,
            "id_usuario_actualizo" => auth()->id() ?? 1,
            "id_nivel" => $request->id_nivel,
            "ip_actualizo" => $request->ip(),
        ]);

        return response()->json([
            "ok" => true,
            "message" => "Asignatura actualizada con éxito"
        ], 200);

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje : " . $e->getMessage());
        Log::error("Linea : " . $e->getLine());

        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor"
        ], 500);
    }
}



public function showAsignatura()
{
    try {
        // Realiza un join con la tabla de niveles para obtener la información adicional
        $asignaturas = AsignaturaModel::select(
            "materias.id_materia", 
            "materias.descripcion", 
            "materias.fecha_creacion", 
            "materias.estado",
            "nivel.id_nivel", 
            "materias.fecha_actualizacion",
            "nivel.termino"    // Selecciona el término
        )
        ->join('nivel', 'materias.id_nivel', '=', 'nivel.id_nivel')  // Une la tabla de niveles
        ->whereIn("materias.estado", ["A", "I"])  // Solo selecciona asignaturas activas o inactivas
        ->get();
        Log::info('Verificación de existencia de usuario completada.', ['asignaturas' => $asignaturas]);

        return response()->json([
            "ok" => true,
            "data" => $asignaturas
        ], 200);

    } catch (Exception $e) {
        Log::error(__FILE__ . " > " . __FUNCTION__);
        Log::error("Mensaje : " . $e->getMessage());
        Log::error("Linea : " . $e->getLine());

        return response()->json([
            "ok" => false,
            "message" => "Error interno en el servidor"
        ], 500);
    }
}

}
