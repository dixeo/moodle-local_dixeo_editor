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
 * French language strings for the Dixeo Editor plugin.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aipanelopen'] = 'Ouvrir le panneau IA';
$string['aipaneltitle'] = 'Éditeur Dixeo';
$string['apply'] = 'OK';
$string['audience_beginner'] = 'Débutant';
$string['audience_expert'] = 'Expert';
$string['audience_intermediate'] = 'Intermédiaire';
$string['cancel'] = "Quitter l'édition";
$string['cancelmodeconfirm'] = "Êtes-vous sûr de vouloir quitter le mode édition ? Toutes les modifications seront perdues.";
$string['cancelshort'] = 'Annuler';
$string['close'] = 'Fermer';
$string['contentmodificationsuccess'] = 'Modification du contenu réussie !';
$string['displaymode'] = 'Mode d\'affichage';
$string['displaymode_dockbottom'] = 'Ancrer en bas';
$string['displaymode_dockleft'] = 'Ancrer à gauche';
$string['displaymode_dockright'] = 'Ancrer à droite';
$string['displaymode_float'] = 'Fenêtre flottante';
$string['displaymode_menu'] = 'Options d\'affichage du panneau';
$string['dixeo_editor:editpage'] = "Permet à l'utilisateur d'accéder à l'édition par IA de contenu d'activité page";
$string['editcontent'] = "Éditeur IA Dixeo";
$string['editorsessiondiscarded'] = 'Session d\'édition abandonnée';
$string['editorsessionexpired'] = 'Session d\'édition expirée';
$string['enrich'] = 'Enrichir';
$string['enrichprompt'] = 'Améliore le contenu à des fins éducatives en ajoutant des explications détaillées, des exemples pertinents et du contexte pour rendre le matériel plus complet et engageant pour les apprenants. Assure clarté, précision et un ton accessible adapté à un public diversifié';
$string['error:generic'] = 'Une erreur inattendue est survenue. Veuillez réessayer.';
$string['eventregeneratecancelled'] = 'Régénération de module Dixeo annulée';
$string['eventregeneratecancelleddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a annulé la tâche de régénération Dixeo \'{$a->jobid}\' pour le module \'{$a->cmid}\' du cours \'{$a->courseid}\'.';
$string['eventregeneratecompleted'] = 'Régénération de module Dixeo terminée';
$string['eventregeneratecompleteddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a reçu la tâche de régénération Dixeo terminée \'{$a->jobid}\' pour le module \'{$a->cmid}\' du cours \'{$a->courseid}\'.';
$string['eventregeneratestarted'] = 'Régénération de module Dixeo démarrée';
$string['eventregeneratestarteddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a démarré la tâche de régénération Dixeo \'{$a->jobid}\' pour le module \'{$a->cmid}\' du cours \'{$a->courseid}\'.';
$string['generate'] = 'Générer';
$string['generating'] = 'Génération...';
$string['length_expand'] = 'Développer';
$string['length_shorten'] = 'Raccourcir';
$string['loading'] = 'Génération en cours, veuillez patientier...';
$string['menu_audience'] = 'Public';
$string['menu_length'] = 'Longueur';
$string['menu_structure'] = 'Structure';
$string['menu_tone'] = 'Ton';
$string['menu_tools'] = 'Outils';
$string['panelclose'] = 'Fermer le panneau IA';
$string['pluginname'] = 'Dixeo Editeur IA';
$string['prettify'] = 'Embellir';
$string['prettifyprompt'] = "Améliore le contenu à des fins éducatives en le rendant plus attrayant visuellement à l'aide des principes modernes de conception. Ajoute des couleurs appropriées, du gras, de l'italique et d'autres styles de mise en forme pour mettre en évidence les termes clés, les titres et les idées. Utilise une esthétique propre et engageante sans modifier le contenu original";

$string['privacy:metadata:files'] = 'Draft images generated while editing an activity, stored until the editor session is saved or discarded.';
$string['privacy:metadata:namespace'] = 'Espace de noms API Dixeo optionnel configuré pour le site.';
$string['privacy:metadata:preference:panel_layout'] = 'Disposition préférée du panneau IA de l\'éditeur de contenu (ancrage ou fenêtre flottante).';
$string['privacy:metadata:session'] = 'Editor sessions that track draft image storage for a course module.';
$string['privacy:metadata:session:cmid'] = 'The course module being edited.';
$string['privacy:metadata:session:slideid'] = 'The slideshow slide being edited, when applicable.';
$string['privacy:metadata:session:status'] = 'Whether the session is active, saved, or discarded.';
$string['privacy:metadata:session:timecreated'] = 'The time when the editor session was created.';
$string['privacy:metadata:session:timemodified'] = 'The time when the editor session was last modified.';
$string['privacy:metadata:session:userid'] = 'The user who owns the editor session.';
$string['privacy:metadata:userid'] = 'L\'identifiant utilisateur Moodle associé à la demande de régénération.';
$string['privacy:path:sessions'] = 'Editor sessions';
$string['prompt_audience_beginner'] = "Adapte le contenu aux apprenants débutants en simplifiant la terminologie, en expliquant les notions de base et en utilisant des exemples clairs. Assure un rythme accessible et évite toute complexité inutile.";
$string['prompt_audience_expert'] = "Adapte le contenu aux apprenants experts en augmentant la précision technique et la profondeur. Utilise une terminologie avancée lorsque pertinent, ajoute des nuances utiles et maintiens un style professionnel concis.";
$string['prompt_audience_intermediate'] = "Adapte le contenu aux apprenants intermédiaires en équilibrant clarté et profondeur. Préserve les concepts clés, introduis un niveau de détail technique modéré et ajoute des exemples pratiques pour renforcer la compréhension.";
$string['prompt_length_expand'] = 'Développe le contenu à des fins éducatives en ajoutant des explications détaillées, des exemples pertinents et du contexte explicatif. Garde un contenu précis, cohérent et engageant pour les apprenants.';
$string['prompt_length_shorten'] = 'Condense le contenu à des fins éducatives en supprimant les redondances et en simplifiant la formulation, tout en conservant le sens essentiel. Garde un texte clair, concis et facile à comprendre.';
$string['prompt_structure_conclusion'] = "Améliore le contenu à des fins éducatives en ajoutant une conclusion concise à la fin. Résume les points clés, renforce les idées les plus importantes et propose une clôture claire.";
$string['prompt_structure_headings'] = "Améliore le contenu à des fins éducatives en ajoutant des titres clairs et descriptifs. Crée une hiérarchie logique des titres pour faciliter la lecture et le balayage, tout en préservant le sens et les informations d'origine.";
$string['prompt_structure_intro'] = "Améliore le contenu à des fins éducatives en ajoutant une introduction concise au début. Résume le sujet, l'objectif d'apprentissage et les résultats attendus dans un ton clair et accessible.";
$string['prompt_structure_reorganize'] = "Réorganise le contenu à des fins éducatives en améliorant sa structure et son enchaînement. Réordonne sections et paragraphes pour une progression logique des idées, réduis les répétitions et conserve toutes les informations clés avec exactitude.";
$string['prompt_tone_casual'] = "Réécris le contenu dans un ton plus décontracté et convivial tout en préservant l'exactitude factuelle et la valeur pédagogique. Garde des explications claires, accessibles et faciles à suivre.";
$string['prompt_tone_formal'] = "Réécris le contenu dans un ton plus formel et professionnel tout en préservant l'exactitude factuelle et la valeur pédagogique. Utilise un langage précis, une structure claire et une terminologie cohérente.";
$string['prompt_tools_grammar'] = "Relis et corrige la grammaire, l'orthographe, la ponctuation et la syntaxe tout en conservant le sens et le ton d'origine. Améliore la clarté et la lisibilité sans modifier l'intention pédagogique.";
$string['prompt_tools_translate'] = 'Traduis le contenu en LANGUE en préservant le sens, la structure et l\'intention pédagogique. Garde une terminologie cohérente et assure un rendu naturel pour les locuteurs natifs.';
$string['promptplaceholder'] = "Instructions d'édition par l'IA";
$string['redo'] = 'Rétablir';
$string['save'] = 'Sauvegarder';
$string['structure_conclusion'] = 'Ajouter une conclusion';
$string['structure_headings'] = 'Ajouter des titres';
$string['structure_intro'] = 'Ajouter une introduction';
$string['structure_reorganize'] = 'Réorganiser / Réordonner';
$string['taskcleanupeditorsessions'] = 'Nettoyer les sessions d\'édition obsolètes';
$string['tone_casual'] = 'Décontracté';
$string['tone_formal'] = 'Formel';
$string['tools_grammar'] = 'Vérification grammaticale';
$string['tools_translate'] = 'Traduire';
$string['translate'] = 'Traduire';
$string['translateprompt'] = 'Traduis le contenu en LANGUE';
$string['undo'] = 'Annuler';
$string['unexpectederror'] = 'Une erreur inattendue est survenue. Veuillez réessayer.';
