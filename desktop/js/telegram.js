
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
$("#table_cmd").delegate(".listEquipementAction", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info', subType: 'string'}, eqLogic : {eqType_name : 'geoloc'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.attr('data-input') + ']');
        calcul.atCaret('insert', result.human);
    });
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
    tr += '<input class="cmdAttr" data-l1key="id" style="display:none;" />';
    tr += '<input class="cmdAttr" data-l1key="type" style="display:none;" />';
    tr += '<input class="cmdAttr" data-l1key="subtype" value="message" style="display:none;" />';
    tr += '</td>';
    if (!isset(_cmd.type) || _cmd.type == 'action') {
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="configuration" data-l2key="title"></span>';
        tr += '</td>';
        tr += '<td>';
        if(_cmd.logicalId != 'alluser'){
            tr += '<select class="form-control cmdAttr input-sm" data-l1key="configuration" data-l2key="user"></select>';
        }
        tr += '</td>';
        tr += '<td>';
        tr += '<div class="input-group">';
        tr += '<input class="cmdAttr form-control" data-l1key="configuration" data-l2key="cmdgeoloc">';
        tr += '<span class="input-group-btn">';
        tr += '<a class="btn btn-default cursor listEquipementAction" data-input="cmdgeoloc"><i class="fa fa-list-alt "></i></a>';
        tr += '</span>';
        tr += '</div>';
        tr += '</td>';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="configuration" data-l2key="first_name"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="configuration" data-l2key="last_name"></span>';
        tr += '</td>';
    } else {
        tr += '<td>';
        tr += '</td>';
        tr += '<td>';
        tr += '</td>';
        tr += '<td>';
        tr += '</td>';
        tr += '<td>';
        tr += '</td>';
        tr += '<td>';
        tr += '</td>';
    }
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Visible}}</label></span> ';
    if ((!isset(_cmd.type) || _cmd.type == 'action') && _cmd.logicalId != 'alluser') {
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="interact" checked/>{{Interactions}}</label></span> ';
    }
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    var tr = $('#table_cmd tbody tr:last');
    jeedom.user.all({
      error: function (error) {
        $('#div_alert').showAlert({message: error.message, level: 'danger'});
    },
    success: function (data) {
        var option = '<option value="">Aucun</option>';
        for (var i in data) {
          option += '<option value="' + data[i].id + '">' + data[i].login + '</option>';
      }
      tr.find('.cmdAttr[data-l1key=configuration][data-l2key=user]').empty().append(option);
      tr.setValues(_cmd, '.cmdAttr');
      modifyWithoutSave = false;
  }
});
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}
