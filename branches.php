<?php
// List branches by surname
//
// webtrees: Web based Family History software
// Copyright (C) 2013 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2009 PGV Development Team.  All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

define('WT_SCRIPT_NAME', 'branches.php');
require './includes/session.php';

$surn        = WT_Filter::get('surname');
$soundex_std = WT_Filter::getBool('soundex_std');
$soundex_dm  = WT_Filter::getBool('soundex_dm');
$ged         = WT_Filter::get('ged');
if (empty($ged)) {
	$ged = $GEDCOM;
}

$user_ancestors=array();
if (WT_USER_GEDCOM_ID) {
	load_ancestors_array(WT_Individual::getInstance(WT_USER_GEDCOM_ID), 1);
}

$controller=new WT_Controller_Page();
if ($surn) {
	$controller->setPageTitle(/* I18N: %s is a surname */ WT_I18N::translate('Branches of the %s family', WT_Filter::escapeHtml($surn)));
} else {
	$controller->setPageTitle(WT_I18N::translate('Branches'));
}
$controller
	->pageHeader()
	->addExternalJavascript(WT_STATIC_URL.'js/autocomplete.js');

?>
<div id="branches-page">
<form name="surnlist" id="surnlist" action="?">
	<table class="facts_table width50">
		<tr>
			<td class="descriptionbox">
				<?php echo WT_Gedcom_Tag::getLabel('SURN'); ?></td>
			<td class="optionbox">
				<input type="text" name="surname" id="SURN" value="<?php echo WT_Filter::escapeHtml($surn); ?>" dir="auto">
				<input type="hidden" name="ged" id="ged" value="<?php echo $ged; ?>">
				<input type="submit" value="<?php echo WT_I18N::translate('View'); ?>">
				<p><?php echo WT_I18N::translate('Phonetic search'); ?></p>
				<p>
					<input type="checkbox" name="soundex_std" id="soundex_std" value="1" <?php if ($soundex_std) echo ' checked="checked"'; ?>>
					<label for="soundex_std"><?php echo WT_I18N::translate('Russell'); ?></label>
					<input type="checkbox" name="soundex_dm" id="soundex_dm" value="1" <?php if ($soundex_dm) echo ' checked="checked"'; ?>>
					<label for="soundex_dm"><?php echo WT_I18N::translate('Daitch-Mokotoff'); ?></label>
				</p>
			</td>
		</tr>
	</table>
</form>
<?php
//-- results
if ($surn) {
	echo '<fieldset><legend><i class="icon-patriarch"></i> ', $surn, '</legend>';
	$indis = indis_array($surn, $soundex_std, $soundex_dm);
	usort($indis, array('WT_Individual', 'CompareBirtDate'));
	echo '<ol>';
	foreach ($indis as $person) {
		$famc = $person->getPrimaryChildFamily();
		// Don't show INDIs with parents in the list, as they will be shown twice.
		if ($famc) {
			foreach ($famc->getSpouses() as $parent) {
				if (in_array($parent, $indis, true)) {
					continue 2;
				}
			}
		}
		print_fams($person);
	}
	echo '</ol>';
	echo '</fieldset>';
}
echo '</div>'; // close branches-page

if (false) {
	// These messages were added (briefly) and translated.
	// Keep them in the translation template, as we will want them in the future
	WT_I18N::translate('Collapse all');
	WT_I18N::translate('Expand all');
}

function print_fams(WT_Individual $person, WT_Family $family=null) {
	global $surn, $soundex_std, $soundex_dm, $user_ancestors;
	// select person name according to searched surname
	$person_name = "";
	foreach ($person->getAllNames() as $n=>$name) {
		list($surn1) = explode(",", $name['sort']);
		if (
			// one name is a substring of the other
			stripos($surn1, $surn)!==false ||
			stripos($surn, $surn1)!==false ||
			// one name sounds like the other
			$soundex_std && WT_Soundex::compare(WT_Soundex::soundex_std($surn1), WT_Soundex::soundex_std($surn)) ||
			$soundex_dm  && WT_Soundex::compare(WT_Soundex::soundex_dm ($surn1), WT_Soundex::soundex_dm ($surn))
		) {
			$person_name = $name['full'];
			break;
		}
	}
	if (empty($person_name)) {
		echo '<li title="', strip_tags($person->getFullName()), '">', $person->getSexImage(), '…</li>';
		return;
	}
	// current indi
	echo '<li>';
	$class = '';
	$sosa = array_search($person->getXref(), $user_ancestors);
	if ($sosa) {
		$class = 'search_hit';
		$sosa = '<a target="_blank" dir="ltr" class="details1 '.$person->getBoxStyle().'" title="'.WT_I18N::translate('Sosa').'" href="relationship.php?pid2='.WT_USER_ROOT_ID.'&amp;pid1='.$person->getXref().'">&nbsp;'.$sosa.'&nbsp;</a>'.sosa_gen($sosa);
	}
	$current = $person->getSexImage().
		'<a target="_blank" class="'.$class.'" href="'.$person->getHtmlUrl().'">'.$person_name.'</a> '.
		$person->getLifeSpan().' '.$sosa;
	if ($family) {
		$pedi = '';
		foreach ($person->getFacts('FAMC') as $fact) {
			if ($fact->getTarget() === $family) {
				$pedi = $fact->getAttribute('PEDI');
				break;
			}
		}
		if ($pedi && $pedi!='birth') {
			$label = WT_Gedcom_Code_Pedi::getValue($pedi, $person);
			$current = '<span class="red">'.$label.'</span> '.$current;
		}
	}
	// spouses and children
	if (count($person->getSpouseFamilies())<1) {
		echo $current;
	}
	foreach ($person->getSpouseFamilies() as $family) {
		$txt = $current;
		$spouse = $family->getSpouse($person);
		if ($spouse) {
			$class = '';
			$sosa2 = array_search($spouse->getXref(), $user_ancestors);
			if ($sosa2) {
				$class = 'search_hit';
				$sosa2 = '<a target="_blank" dir="ltr" class="details1 '.$spouse->getBoxStyle().'" title="'.WT_I18N::translate('Sosa').'" href="relationship.php?pid2='.WT_USER_ROOT_ID.'&amp;pid1='.$spouse->getXref().'">&nbsp;'.$sosa2.'&nbsp;</a>'.sosa_gen($sosa2);
			}
			$marriage_year=$family->getMarriageYear();
			if ($marriage_year) {
				$txt .= ' <a href="'.$family->getHtmlUrl().'">';
				$txt .= '<span class="details1" title="'.strip_tags($family->getMarriageDate()->Display()).'"><i class="icon-rings"></i>'.$marriage_year.'</span></a>';
			}
			else if ($family->getFirstFact('MARR')) {
				$txt .= ' <a href="'.$family->getHtmlUrl().'">';
				$txt .= '<span class="details1" title="'.WT_I18N::translate('yes').'"><i class="icon-rings"></i></span></a>';
			}
		$txt .=
			$spouse->getSexImage().
			' <a class="'.$class.'" href="'.$spouse->getHtmlUrl().'">'.$spouse->getFullName().'</a> '.$spouse->getLifeSpan().' '.$sosa2;
		}
		echo $txt;
		echo '<ol>';
		foreach ($family->getChildren() as $child) {
			print_fams($child, $family);
		}
		echo '</ol>';
	}
	echo '</li>';
}

function load_ancestors_array($person, $sosa=1) {
	global $user_ancestors;
	if ($person) {
		$user_ancestors[$sosa]=$person->getXref();
		foreach ($person->getChildFamilies() as $family) {
			foreach ($family->getSpouses() as $parent) {
				load_ancestors_array($parent, $sosa*2+($parent->getSex()=='F'));
			}
		}
	}
}

function indis_array($surn, $soundex_std, $soundex_dm) {
	$sql=
		"SELECT DISTINCT i_id AS xref, i_file AS gedcom_id, i_gedcom AS gedcom".
		" FROM `##individuals`".
		" JOIN `##name` ON (i_id=n_id AND i_file=n_file)".
		" WHERE n_file=?".
		" AND n_type!=?".
		" AND (n_surn=? OR n_surname=?";
	$args=array(WT_GED_ID, '_MARNM', $surn, $surn);
	if ($soundex_std) {
		foreach (explode(':', WT_Soundex::soundex_std($surn)) as $value) {
			$sql .= " OR n_soundex_surn_std LIKE CONCAT('%', ?, '%')";
			$args[]=$value;
		}
	}
	if ($soundex_dm) {
		foreach (explode(':', WT_Soundex::soundex_dm($surn)) as $value) {
			$sql .= " OR n_soundex_surn_dm LIKE CONCAT('%', ?, '%')";
			$args[]=$value;
		}
	}
	$sql .= ')';
	$rows=
		WT_DB::prepare($sql)
		->execute($args)
		->fetchAll();
	$data=array();
	foreach ($rows as $row) {
		$data[]=WT_Individual::getInstance($row->xref, $row->gedcom_id, $row->gedcom);
	}
	return $data;
}

function sosa_gen($sosa) {
	$gen = (int)log($sosa, 2)+1;
	return '<sup title="'.WT_I18N::translate('Generation').'">'.$gen.'</sup>';
}