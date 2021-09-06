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
<h3>GMV-Schnittstelle - Geänderte Werte:</h3>
<table>
  <thead>
    <tr>
      <th>Attribut</th>
      <th>Alter Wert</th>
      <th>Neuer Wert</th>
    </tr>
  </thead>
  <tbody>
  {foreach from=$change_set item=change_data}
    <tr>
      <td>{$change_data.label}</td>
      <td>{$change_data.old_value}</td>
      <td>{$change_data.new_value}</td>
    </tr>
  {/foreach}
  </tbody>
</table>
