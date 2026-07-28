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
 * Italian language strings for the Dixeo Editor plugin.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aipanelopen'] = 'Apri pannello IA';
$string['aipaneltitle'] = 'Editor Dixeo';
$string['apply'] = 'OK';
$string['audience_beginner'] = 'Principiante';
$string['audience_expert'] = 'Esperto';
$string['audience_intermediate'] = 'Intermedio';
$string['cancel'] = 'Esci dalla modalità di modifica';
$string['cancelmodeconfirm'] = 'Sei sicuro di voler uscire dalla modalità di modifica? Tutte le modifiche andranno perse.';
$string['cancelshort'] = 'Annulla';
$string['close'] = 'Chiudi';
$string['contentmodificationsuccess'] = 'Contenuto modificato con successo!';
$string['displaymode'] = 'Modalità di visualizzazione';
$string['displaymode_dockbottom'] = 'Aggancia in basso';
$string['displaymode_dockleft'] = 'Aggancia a sinistra';
$string['displaymode_dockright'] = 'Aggancia a destra';
$string['displaymode_float'] = 'Finestra mobile';
$string['displaymode_menu'] = 'Opzioni di visualizzazione del pannello';
$string['dixeo_editor:editpage'] = "Consente a un utente di accedere alle funzionalità IA per modificare un'attività di una pagina";
$string['editcontent'] = 'Editor IA Dixeo';
$string['editorsessiondiscarded'] = 'Sessione dell\'editor scartata';
$string['editorsessionexpired'] = 'Sessione dell\'editor scaduta';
$string['enrich'] = 'Arricchisci';
$string['enrichprompt'] = 'Migliora il contenuto per scopi educativi aggiungendo spiegazioni dettagliate, esempi pertinenti e contesto per rendere il materiale più completo e coinvolgente per gli studenti. Garantisci chiarezza, accuratezza e un tono accessibile adatto a un pubblico diversificato';
$string['error:generic'] = 'Si è verificato un errore imprevisto. Riprova.';
$string['eventregeneratecancelled'] = 'Rigenerazione modulo Dixeo annullata';
$string['eventregeneratecancelleddesc'] = 'L\'utente con id \'{$a->userid}\' ha annullato il job di rigenerazione Dixeo \'{$a->jobid}\' per il modulo \'{$a->cmid}\' nel corso \'{$a->courseid}\'.';
$string['eventregeneratecompleted'] = 'Rigenerazione modulo Dixeo completata';
$string['eventregeneratecompleteddesc'] = 'L\'utente con id \'{$a->userid}\' ha ricevuto il job di rigenerazione Dixeo completato \'{$a->jobid}\' per il modulo \'{$a->cmid}\' nel corso \'{$a->courseid}\'.';
$string['eventregeneratestarted'] = 'Rigenerazione modulo Dixeo avviata';
$string['eventregeneratestarteddesc'] = 'L\'utente con id \'{$a->userid}\' ha avviato il job di rigenerazione Dixeo \'{$a->jobid}\' per il modulo \'{$a->cmid}\' nel corso \'{$a->courseid}\'.';
$string['generate'] = 'Genera';
$string['generating'] = 'Generazione in corso...';
$string['length_expand'] = 'Espandi';
$string['length_shorten'] = 'Accorcia';
$string['loading'] = 'Generazione del nuovo contenuto, attendere prego...';
$string['menu_audience'] = 'Pubblico';
$string['menu_length'] = 'Lunghezza';
$string['menu_structure'] = 'Struttura';
$string['menu_tone'] = 'Tono';
$string['menu_tools'] = 'Strumenti';
$string['panelclose'] = 'Chiudi pannello IA';
$string['pluginname'] = 'Editor IA Dixeo';
$string['prettify'] = 'Abbellire';
$string['prettifyprompt'] = 'Migliora il contenuto per scopi educativi rendendolo più attraente visivamente utilizzando i principi di design moderni. Aggiungi colori appropriati, grassetto, corsivo e altri stili di formattazione per enfatizzare i termini chiave, i titoli e le idee. Utilizza un’estetica pulita e coinvolgente senza alterare il contenuto originale';

$string['privacy:metadata:files'] = 'Draft images generated while editing an activity, stored until the editor session is saved or discarded.';
$string['privacy:metadata:moduletype'] = 'Il tipo di attività Moodle modificata (ad esempio pagina, etichetta o slideshow).';
$string['privacy:metadata:namespace'] = 'Namespace API Dixeo opzionale configurato per il sito.';
$string['privacy:metadata:preference:panel_layout'] = 'Layout preferito del pannello IA dell\'editor di contenuti (ancoraggio o finestra mobile).';
$string['privacy:metadata:session'] = 'Editor sessions that track draft image storage for a course module.';
$string['privacy:metadata:session:cmid'] = 'The course module being edited.';
$string['privacy:metadata:session:slideid'] = 'The slideshow slide being edited, when applicable.';
$string['privacy:metadata:session:status'] = 'Whether the session is active, saved, or discarded.';
$string['privacy:metadata:session:timecreated'] = 'The time when the editor session was created.';
$string['privacy:metadata:session:timemodified'] = 'The time when the editor session was last modified.';
$string['privacy:metadata:session:userid'] = 'The user who owns the editor session.';
$string['privacy:metadata:userid'] = 'L\'identificatore utente Moodle associato alla richiesta di rigenerazione.';
$string['privacy:path:sessions'] = 'Editor sessions';
$string['prompt_audience_beginner'] = 'Adatta il contenuto a studenti principianti semplificando la terminologia, spiegando i concetti fondamentali e usando esempi chiari. Garantisci un ritmo accessibile ed evita complessità non necessarie.';
$string['prompt_audience_expert'] = 'Adatta il contenuto a studenti esperti aumentando precisione tecnica e profondità. Usa terminologia avanzata quando opportuno, includi dettagli sfumati e mantieni un linguaggio professionale e conciso.';
$string['prompt_audience_intermediate'] = 'Adatta il contenuto a studenti di livello intermedio bilanciando chiarezza e profondità. Mantieni i concetti chiave, introduci un livello tecnico moderato e includi esempi pratici per rafforzare la comprensione.';
$string['prompt_length_expand'] = 'Espandi il contenuto per finalità educative aggiungendo spiegazioni dettagliate, esempi pertinenti e contesto chiarificatore. Mantieni il materiale accurato, coerente e coinvolgente per gli studenti.';
$string['prompt_length_shorten'] = 'Condensa il contenuto per finalità educative rimuovendo ridondanze e semplificando la formulazione, preservando tutto il significato essenziale. Mantienilo chiaro, conciso e facile da comprendere.';
$string['prompt_structure_conclusion'] = 'Migliora il contenuto per finalità educative aggiungendo una conclusione concisa alla fine. Riassumi i punti chiave, rafforza le idee più importanti e fornisci una chiusura chiara.';
$string['prompt_structure_headings'] = 'Migliora il contenuto per finalità educative aggiungendo titoli chiari e descrittivi. Crea una gerarchia logica dei titoli per aumentare leggibilità e scansione, preservando significato e informazioni originali.';
$string['prompt_structure_intro'] = 'Migliora il contenuto per finalità educative aggiungendo un\'introduzione concisa all\'inizio. Riassumi argomento, obiettivo di apprendimento e risultati attesi con un tono chiaro e accessibile.';
$string['prompt_structure_reorganize'] = 'Riorganizza il contenuto per finalità educative migliorandone struttura e flusso. Riordina sezioni e paragrafi affinché le idee seguano una progressione logica, riduci le ripetizioni e mantieni complete e accurate tutte le informazioni chiave.';
$string['prompt_tone_casual'] = 'Riscrivi il contenuto con un tono più informale e amichevole, mantenendo accuratezza fattuale e valore educativo. Mantieni spiegazioni chiare, accessibili e facili da seguire per gli studenti.';
$string['prompt_tone_formal'] = 'Riscrivi il contenuto con un tono più formale e professionale, mantenendo accuratezza fattuale e valore educativo. Usa linguaggio preciso, struttura chiara e terminologia coerente.';
$string['prompt_tools_grammar'] = 'Rivedi e correggi grammatica, ortografia, punteggiatura e sintassi preservando significato e tono originali. Migliora chiarezza e leggibilità senza alterare il contenuto educativo previsto.';
$string['prompt_tools_translate'] = 'Traduci il contenuto in LINGUA preservando significato, struttura e finalità educativa. Mantieni terminologia coerente e assicurati che il testo finale risulti naturale per parlanti nativi.';
$string['promptplaceholder'] = "Istruzioni di modifica per l'IA";
$string['redo'] = 'Ripeti';
$string['save'] = 'Salva';
$string['structure_conclusion'] = 'Aggiungi una conclusione';
$string['structure_headings'] = 'Aggiungi titoli';
$string['structure_intro'] = 'Aggiungi un\'introduzione';
$string['structure_reorganize'] = 'Riorganizza / Riordina';
$string['taskcleanupeditorsessions'] = 'Pulisci le sessioni dell\'editor obsolete';
$string['tone_casual'] = 'Informale';
$string['tone_formal'] = 'Formale';
$string['tools_grammar'] = 'Controllo grammaticale';
$string['tools_translate'] = 'Traduci';
$string['translate'] = 'Traduci';
$string['translateprompt'] = 'Traduci il contenuto in LINGUA';
$string['undo'] = 'Annulla';
$string['unexpectederror'] = 'Si è verificato un errore imprevisto. Riprova.';
