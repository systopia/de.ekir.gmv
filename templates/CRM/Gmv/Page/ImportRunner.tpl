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

<h3>Import-Prozess: {$import_id}</h3>

<div id="help">Aktuell kann der Import-Prozess nur manuell über die Server-Konsole ausgeführt werden. Ggf wird es später aber eine Umsetzung geben, die direkt aus dem System heraus aufgerufen wird.</div>

<div>
  Schritte für den Import
  <ol>
    <li>Auf dem Server einloggen: <br/><code>ssh civirm@vs1170.mymanaged.host</code></li>
    <li>Wartungsmodus setzen:<br/><code>cvi drush {$environment} vset maintenance_mode 1</code></li>
    <li>Backup machen:<br/><code>cvi backup -c {$environment} GMVImport</code></li>
    <li>Dort eingeben: <br/><code>nohup cvi drush {$environment} cvapi GMV.sync data={$import_id} &</code></li>
    <li>Dann Log verfolgen: <br/><code>tail -f {$full_path}/import.log</code></li>
    <li>Ergebnisse prüfen</code></li>
    <li>Wartungsmodus aufheben:<br/><code>cvi drush {$environment} vset maintenance_mode 0</code></li>
  </ol>
</div>