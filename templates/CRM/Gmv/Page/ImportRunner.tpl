{*}-------------------------------------------------------+
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
+-------------------------------------------------------*}

<h3>Process: {$import_id}</h3>

<textarea style="overflow:auto; width:100%; resize: none;" name="import_log" placeholder="Starting...."></textarea>

{literal}
<script>
  cj(document).ready(function () {
    // kick off importer process
    CRM.api3('Contact', 'get', ['first_name' => 'test']);
    cj("[name=import_log]").val("TEST");
  });
</script>
{/literal}