<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for the Dixeo Editor plugin.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aipanelopen'] = 'Abrir panel de IA';
$string['aipaneltitle'] = 'Editor Dixeo';
$string['apply'] = 'OK';
$string['audience_beginner'] = 'Principiante';
$string['audience_expert'] = 'Experto';
$string['audience_intermediate'] = 'Intermedio';
$string['cancel'] = 'Salir del modo de edición';
$string['cancelmodeconfirm'] = '¿Seguro que quieres salir del modo de edición? Se perderán todas las modificaciones.';
$string['cancelshort'] = 'Cancelar';
$string['close'] = 'Cerrar';
$string['contentmodificationsuccess'] = '¡Contenido modificado con éxito!';
$string['displaymode'] = 'Modo de visualización';
$string['displaymode_dockbottom'] = 'Acoplar abajo';
$string['displaymode_dockleft'] = 'Acoplar a la izquierda';
$string['displaymode_dockright'] = 'Acoplar a la derecha';
$string['displaymode_float'] = 'Ventana flotante';
$string['displaymode_menu'] = 'Opciones de visualización del panel';
$string['dixeo_editor:editpage'] = 'Permite a un usuario acceder a funcionalidades IA para editar una actividad de página';
$string['editcontent'] = 'Editor de Contenido IA';
$string['enrich'] = 'Enriquecer';
$string['enrichprompt'] = 'Mejora el contenido con fines educativos añadiendo explicaciones detalladas, ejemplos relevantes y contexto para hacer que el material sea más completo y atractivo para los estudiantes. Asegúrate de que sea claro, preciso y con un tono accesible para una audiencia diversa';
$string['error:generic'] = 'Se produjo un error inesperado. Inténtalo de nuevo.';
$string['generate'] = 'Generar';
$string['generating'] = 'Generando...';
$string['length_expand'] = 'Ampliar';
$string['length_shorten'] = 'Acortar';
$string['loading'] = 'Generando nuevo contenido, por favor espere...';
$string['menu_audience'] = 'Audiencia';
$string['menu_length'] = 'Longitud';
$string['menu_structure'] = 'Estructura';
$string['menu_tone'] = 'Tono';
$string['menu_tools'] = 'Herramientas';
$string['panelclose'] = 'Cerrar panel de IA';
$string['pluginname'] = 'Editor Dixeo IA';
$string['prettify'] = 'Embellecer';
$string['prettifyprompt'] = 'Mejora el contenido con fines educativos haciéndolo más atractivo visualmente utilizando principios modernos de diseño. Añade colores apropiados, negritas, cursivas y otros estilos de formato para resaltar términos clave, encabezados e ideas. Utiliza una estética limpia y atractiva sin alterar el contenido original';
$string['privacy:metadata:context'] = 'Contenido del módulo o diapositiva, estructura del curso y contexto circundante enviados a la IA para edición.';
$string['privacy:metadata:courseid'] = 'El identificador del curso asociado a la actividad que se edita.';
$string['privacy:metadata:externalpurpose'] = 'Las instrucciones de edición y el contenido de la actividad se envían a la API de Dixeo para regenerar o refinar el contenido del módulo. El plugin no almacena cargas de IA en Moodle.';
$string['privacy:metadata:instructions'] = 'Instrucciones de edición proporcionadas por el usuario para la IA.';
$string['privacy:metadata:moduletype'] = 'El tipo de actividad de Moodle que se edita (por ejemplo página, etiqueta o presentación).';
$string['privacy:metadata:namespace'] = 'Espacio de nombres opcional de la API Dixeo configurado para el sitio.';
$string['privacy:metadata:preference:panel_layout'] = 'Disposición preferida del panel de IA del editor de contenido (posición acoplada o flotante).';
$string['prompt_audience_beginner'] = 'Adapta el contenido para estudiantes principiantes simplificando la terminología, explicando conceptos fundamentales y utilizando ejemplos claros. Asegura un ritmo accesible y evita complejidad innecesaria.';
$string['prompt_audience_expert'] = 'Adapta el contenido para estudiantes expertos aumentando la precisión técnica y la profundidad. Usa terminología avanzada cuando corresponda, añade matices relevantes y mantén un lenguaje profesional y conciso.';
$string['prompt_audience_intermediate'] = 'Adapta el contenido para estudiantes de nivel intermedio equilibrando claridad y profundidad. Conserva los conceptos centrales, introduce detalle técnico moderado e incluye ejemplos prácticos para reforzar la comprensión.';
$string['prompt_length_expand'] = 'Amplía el contenido con fines educativos añadiendo explicaciones detalladas, ejemplos relevantes y contexto aclaratorio. Mantén el material preciso, coherente y atractivo para los estudiantes.';
$string['prompt_length_shorten'] = 'Condensa el contenido con fines educativos eliminando redundancias y simplificando la redacción, preservando todo el significado esencial. Manténlo claro, conciso y fácil de entender.';
$string['prompt_structure_conclusion'] = 'Mejora el contenido con fines educativos añadiendo una conclusión concisa al final. Resume las ideas clave, refuerza los puntos más importantes y proporciona un cierre claro.';
$string['prompt_structure_headings'] = 'Mejora el contenido con fines educativos añadiendo encabezados claros y descriptivos. Crea una jerarquía de encabezados lógica para facilitar la lectura y el escaneo, preservando el significado y la información original.';
$string['prompt_structure_intro'] = 'Mejora el contenido con fines educativos añadiendo una introducción concisa al inicio. Resume el tema, el objetivo de aprendizaje y los resultados esperados con un tono claro y cercano.';
$string['prompt_structure_reorganize'] = 'Reorganiza el contenido con fines educativos mejorando su estructura y flujo. Reordena secciones y párrafos para que las ideas progresen de forma lógica, reduce repeticiones y conserva toda la información clave con precisión y completitud.';
$string['prompt_tone_casual'] = 'Reescribe el contenido con un tono más casual y cercano, manteniendo la precisión factual y el valor educativo. Conserva explicaciones claras, accesibles y fáciles de seguir para los estudiantes.';
$string['prompt_tone_formal'] = 'Reescribe el contenido con un tono más formal y profesional, manteniendo la precisión factual y el valor educativo. Usa lenguaje preciso, estructura clara y terminología coherente.';
$string['prompt_tools_grammar'] = 'Revisa y corrige gramática, ortografía, puntuación y sintaxis, preservando el significado y el tono original. Mejora la claridad y legibilidad sin cambiar el contenido educativo pretendido.';
$string['prompt_tools_translate'] = 'Traduce el contenido a IDIOMA preservando el significado, la estructura y la intención educativa. Mantén la terminología coherente y asegúrate de que el texto final suene natural para hablantes nativos.';
$string['promptplaceholder'] = 'Instrucciones de edición para la IA';
$string['redo'] = 'Rehacer';
$string['save'] = 'Guardar';
$string['structure_conclusion'] = 'Agregar una conclusión';
$string['structure_headings'] = 'Agregar encabezados';
$string['structure_intro'] = 'Agregar una introducción';
$string['structure_reorganize'] = 'Reorganizar / Reordenar';
$string['tone_casual'] = 'Casual';
$string['tone_formal'] = 'Formal';
$string['tools_grammar'] = 'Revisión gramatical';
$string['tools_translate'] = 'Traducir';
$string['translate'] = 'Traducir';
$string['translateprompt'] = 'Traduce el contenido al IDIOMA';
$string['undo'] = 'Deshacer';
$string['unexpectederror'] = 'Se produjo un error inesperado. Inténtalo de nuevo.';
