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
 * German language strings for the Dixeo Editor plugin.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aipanelopen'] = 'KI-Panel öffnen';
$string['aipaneltitle'] = 'Dixeo-Editor';
$string['apply'] = 'OK';
$string['audience_beginner'] = 'Anfänger';
$string['audience_expert'] = 'Experte';
$string['audience_intermediate'] = 'Fortgeschritten';
$string['cancel'] = 'Bearbeitungsmodus beenden';
$string['cancelmodeconfirm'] = 'Möchten Sie den Bearbeitungsmodus wirklich verlassen? Alle Änderungen gehen verloren.';
$string['cancelshort'] = 'Abbrechen';
$string['close'] = 'Schließen';
$string['contentmodificationsuccess'] = 'Inhalt erfolgreich geändert!';
$string['displaymode'] = 'Anzeigemodus';
$string['displaymode_dockbottom'] = 'Unten andocken';
$string['displaymode_dockleft'] = 'Links andocken';
$string['displaymode_dockright'] = 'Rechts andocken';
$string['displaymode_float'] = 'Schwebendes Fenster';
$string['displaymode_menu'] = 'Anzeigeoptionen für das Panel';
$string['dixeo_editor:editpage'] = 'Ermöglicht Nutzer/innen den Zugriff auf KI-Funktionen zur Bearbeitung einer Seitenaktivität';
$string['editcontent'] = 'Dixeo-KI-Editor';
$string['enrich'] = 'Anreichern';
$string['enrichprompt'] = 'Reichert den Inhalt zu Bildungszwecken durch detaillierte Erklärungen, relevante Beispiele und Kontext an, um das Material umfassender und ansprechender zu gestalten. Stellt Klarheit, Genauigkeit und einen zugänglichen Ton für ein vielfältiges Publikum sicher';
$string['error:generic'] = 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
$string['eventregeneratecancelled'] = 'Dixeo-Modul-Regenerierung abgebrochen';
$string['eventregeneratecancelleddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Dixeo-Regenerierungsauftrag \'{$a->jobid}\' für Kursmodul \'{$a->cmid}\' im Kurs \'{$a->courseid}\' abgebrochen.';
$string['eventregeneratecompleted'] = 'Dixeo-Modul-Regenerierung abgeschlossen';
$string['eventregeneratecompleteddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den abgeschlossenen Dixeo-Regenerierungsauftrag \'{$a->jobid}\' für Kursmodul \'{$a->cmid}\' im Kurs \'{$a->courseid}\' erhalten.';
$string['eventregeneratestarted'] = 'Dixeo-Modul-Regenerierung gestartet';
$string['eventregeneratestarteddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Dixeo-Regenerierungsauftrag \'{$a->jobid}\' für Kursmodul \'{$a->cmid}\' im Kurs \'{$a->courseid}\' gestartet.';
$string['generate'] = 'Generieren';
$string['generating'] = 'Wird generiert...';
$string['length_expand'] = 'Erweitern';
$string['length_shorten'] = 'Verkürzen';
$string['loading'] = 'Neuer Inhalt wird generiert, bitte warten...';
$string['menu_audience'] = 'Zielgruppe';
$string['menu_length'] = 'Länge';
$string['menu_structure'] = 'Struktur';
$string['menu_tone'] = 'Ton';
$string['menu_tools'] = 'Werkzeuge';
$string['panelclose'] = 'KI-Panel schließen';
$string['pluginname'] = 'Dixeo-KI-Editor';
$string['prettify'] = 'Verschönern';
$string['prettifyprompt'] = 'Verbessert den Inhalt zu Bildungszwecken, indem er mit modernen Designprinzipien visuell ansprechender gestaltet wird. Fügt passende Farben, Fett-, Kursiv- und andere Formatierungen hinzu, um Schlüsselbegriffe, Überschriften und Ideen hervorzuheben. Schafft eine klare und ansprechende Ästhetik ohne Änderung des ursprünglichen Inhalts';
$string['privacy:metadata:context'] = 'Modul- oder Folieninhalt, Kursstruktur und umgebender Kontext, die zur KI-Bearbeitung gesendet werden.';
$string['privacy:metadata:courseid'] = 'Die Kennung des Kurses, dem die bearbeitete Aktivität zugeordnet ist.';
$string['privacy:metadata:externalpurpose'] = 'Bearbeitungsanweisungen und Aktivitätsinhalte werden an die Dixeo-API gesendet, um Modulinhalte zu regenerieren oder zu verfeinern. Das Plugin speichert KI-Nutzlasten nicht in Moodle.';
$string['privacy:metadata:instructions'] = 'Vom Benutzer bereitgestellte Bearbeitungsanweisungen für die KI.';
$string['privacy:metadata:moduletype'] = 'Der Moodle-Aktivitätstyp, der bearbeitet wird (z. B. Seite, Textfeld oder Slideshow).';
$string['privacy:metadata:namespace'] = 'Optionaler für die Website konfigurierter Dixeo-API-Namespace.';
$string['privacy:metadata:preference:panel_layout'] = 'Bevorzugtes Layout des KI-Panels im Inhaltseditor (Andockposition oder schwebend).';
$string['prompt_audience_beginner'] = 'Passe den Inhalt an Anfänger an, indem Terminologie vereinfacht, Grundlagen erklärt und klare Beispiele verwendet werden. Sorge für ein zugängliches Tempo und vermeide unnötige Komplexität.';
$string['prompt_audience_expert'] = 'Passe den Inhalt an Expertinnen und Experten an, indem technische Präzision und Tiefe erhöht werden. Verwende bei Bedarf fortgeschrittene Terminologie, ergänze differenzierte Details und halte den Stil professionell und prägnant.';
$string['prompt_audience_intermediate'] = 'Passe den Inhalt an Lernende auf mittlerem Niveau an, indem Klarheit und Tiefe ausgewogen kombiniert werden. Erhalte Kernkonzepte, ergänze moderaten technischen Detailgrad und füge praxisnahe Beispiele hinzu.';
$string['prompt_length_expand'] = 'Erweitere den Inhalt für Bildungszwecke durch detaillierte Erklärungen, relevante Beispiele und klärenden Kontext. Halte den Text präzise, kohärent und ansprechend für Lernende.';
$string['prompt_length_shorten'] = 'Kürze den Inhalt für Bildungszwecke, indem Redundanzen entfernt und Formulierungen vereinfacht werden, ohne wesentliche Bedeutung zu verlieren. Halte ihn klar, knapp und gut verständlich.';
$string['prompt_structure_conclusion'] = 'Verbessere den Inhalt für Bildungszwecke durch ein prägnantes Fazit am Ende. Fasse die wichtigsten Erkenntnisse zusammen, betone zentrale Punkte und schließe klar ab.';
$string['prompt_structure_headings'] = 'Verbessere den Inhalt für Bildungszwecke durch klare und aussagekräftige Überschriften. Erstelle eine logische Überschriftenhierarchie, um Lesbarkeit und Scannbarkeit zu erhöhen, ohne Bedeutung und Informationen zu verändern.';
$string['prompt_structure_intro'] = 'Verbessere den Inhalt für Bildungszwecke durch eine prägnante Einleitung am Anfang. Fasse Thema, Lernziel und erwartete Ergebnisse in einem klaren und zugänglichen Ton zusammen.';
$string['prompt_structure_reorganize'] = 'Strukturiere den Inhalt für Bildungszwecke neu und verbessere Aufbau sowie Lesefluss. Ordne Abschnitte und Absätze logisch, reduziere Wiederholungen und bewahre alle wichtigen Informationen korrekt und vollständig.';
$string['prompt_tone_casual'] = 'Formuliere den Inhalt in einem lockereren und freundlicheren Ton um, ohne sachliche Genauigkeit und Bildungswert zu verlieren. Halte Erklärungen klar, zugänglich und leicht verständlich.';
$string['prompt_tone_formal'] = 'Formuliere den Inhalt in einem formelleren und professionelleren Ton um, ohne sachliche Genauigkeit und Bildungswert zu verlieren. Nutze präzise Sprache, klare Struktur und konsistente Terminologie.';
$string['prompt_tools_grammar'] = 'Prüfe und korrigiere Grammatik, Rechtschreibung, Zeichensetzung und Syntax, ohne Bedeutung und Ton des Originals zu verändern. Verbessere Klarheit und Lesbarkeit ohne inhaltliche Verfälschung.';
$string['prompt_tools_translate'] = 'Übersetze den Inhalt in SPRACHE und bewahre dabei Bedeutung, Struktur und Bildungsabsicht. Achte auf konsistente Terminologie und einen natürlich klingenden Zieltext für Muttersprachler.';
$string['promptplaceholder'] = 'Bearbeitungsanweisungen für die KI';
$string['redo'] = 'Wiederholen';
$string['save'] = 'Speichern';
$string['structure_conclusion'] = 'Fazit hinzufügen';
$string['structure_headings'] = 'Überschriften hinzufügen';
$string['structure_intro'] = 'Einleitung hinzufügen';
$string['structure_reorganize'] = 'Neu strukturieren / umordnen';
$string['tone_casual'] = 'Locker';
$string['tone_formal'] = 'Formell';
$string['tools_grammar'] = 'Grammatikprüfung';
$string['tools_translate'] = 'Übersetzen';
$string['translate'] = 'Übersetzen';
$string['translateprompt'] = 'Inhalt in SPRACHE übersetzen';
$string['undo'] = 'Rückgängig';
$string['unexpectederror'] = 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
