{*-------------------------------------------------------+
| EKIR GMV / ORG DB Synchronisation                      |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*}

<div id="help">Vielleicht vorher noch ein Backup machen...?</div>

<div class="crm-section">
  <div class="label">{$form.gmv_zip.label}</div>
  <div class="content">{$form.gmv_zip.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.gmv_xcm_profile_individuals.label}</div>
  <div class="content">{$form.gmv_xcm_profile_individuals.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.gmv_xcm_profile_organisations.label}</div>
  <div class="content">{$form.gmv_xcm_profile_organisations.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
