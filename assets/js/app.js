/* FleetIQ — Driver Behavior front-end */
var PAGE = window.PAGE || '';
var charts = {};

function fmtNum(n){ return (n===null||n===undefined)?'':Number(n).toLocaleString(); }
function esc(s){ return $('<div>').text(s==null?'':s).html(); }
function typeBadge(label,color){ return '<span class="badge-type" style="background:'+color+'">'+esc(label)+'</span>'; }
function ptsCell(p){ return p>0 ? '<span class="pts-pill">'+p+'</span>' : '<span class="pts-zero">0</span>'; }
function destroyChart(k){ if(charts[k]){ charts[k].destroy(); delete charts[k]; } }
function nextMondayStr(){ var d=new Date(); var add=(8-d.getDay())%7||7; d.setDate(d.getDate()+add);
  var m=(''+(d.getMonth()+1)).padStart(2,'0'), dd=(''+d.getDate()).padStart(2,'0'); return d.getFullYear()+'-'+m+'-'+dd; }

function filterParams(){
  return {
    type: $('#fType').val() || '',
    from: $('#fFrom').val() || '',
    to:   $('#fTo').val() || ''
  };
}

$(function(){
  if(PAGE==='dashboard') initDashboard();
  if(PAGE==='drivers')   initDrivers();
  if(PAGE==='events')    initEvents();
  if(PAGE==='import')    initImport();
  if(PAGE==='users')     initUsers();
  if(PAGE==='profile')   initProfile();
  if(PAGE==='email')     initEmail();
  if(PAGE==='movement')  initMovement();
  if(PAGE==='incidents') initIncidents();
  if(PAGE==='reports')   initReports();
  if(PAGE==='map')       initMapView();
  if(PAGE==='notices')   initNotices();

  $('#applyFilters').on('click', function(){
    if(PAGE==='dashboard') loadDashboard();
    if(PAGE==='events') eventsTable.ajax.reload();
  });
  $('#resetFilters').on('click', function(){
    $('#fType,#fFrom,#fTo').val('');
    if(PAGE==='dashboard') loadDashboard();
    if(PAGE==='events') eventsTable.ajax.reload();
  });
});

/* ===================== DASHBOARD ===================== */
function initDashboard(){
  // honor ?type=&driver_id= deep links
  var p = new URLSearchParams(location.search);
  if(p.get('type')) $('#fType').val(p.get('type'));
  loadDashboard();
}
function loadDashboard(){
  $.getJSON('api/dashboard.php', filterParams(), function(d){
    $('#kEvents').text(fmtNum(d.kpi.total_events));
    $('#kViol').text(fmtNum(d.kpi.violations));
    $('#kIdle').text(fmtNum(d.kpi.idling));
    $('#kDrivers').text(fmtNum(d.kpi.drivers));
    $('#kPoints').text(fmtNum(d.kpi.points));
    renderTypeChart(d.by_type);
    renderTrendChart(d.trend);
    renderWorstChart(d.worst);
  });
}
function renderTypeChart(rows){
  destroyChart('type');
  var ctx = document.getElementById('typeChart');
  charts.type = new Chart(ctx,{
    type:'doughnut',
    data:{labels:rows.map(r=>r.label),datasets:[{data:rows.map(r=>r.count),
      backgroundColor:rows.map(r=>r.color),borderWidth:2,borderColor:'#fff'}]},
    options:{plugins:{legend:{display:false}},cutout:'62%',maintainAspectRatio:false}
  });
  $('#typeLegend').html(rows.map(r=>
    '<span><i style="background:'+r.color+'"></i>'+esc(r.label)+' ('+r.count+')</span>').join(''));
}
function renderTrendChart(rows){
  destroyChart('trend');
  charts.trend = new Chart(document.getElementById('trendChart'),{
    type:'line',
    data:{labels:rows.map(r=>r.d),datasets:[
      {label:'Violations',data:rows.map(r=>+r.viol),borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.12)',fill:true,tension:.35},
      {label:'Idling',data:rows.map(r=>+r.idle),borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.12)',fill:true,tension:.35}
    ]},
    options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},
      scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
  });
}
function renderWorstChart(rows){
  destroyChart('worst');
  charts.worst = new Chart(document.getElementById('worstChart'),{
    type:'bar',
    data:{labels:rows.map(r=>r.label),datasets:[{label:'Demerit Points',data:rows.map(r=>r.points),
      backgroundColor:'#6366f1',borderRadius:6}]},
    options:{maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},
      onClick:function(e,el){ if(el.length){ openDriver(rows[el[0].index].driver_id); } },
      scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
  });
}

/* ===================== DRIVERS ===================== */
function initDrivers(){
  $('#driverTable').DataTable({
    ajax:{url:'api/drivers.php',dataSrc:'data'},
    order:[[0,'asc']],
    pageLength:25,
    columns:[
      {data:'score',render:function(d,t,row){
        return '<span class="score-badge" style="background:'+row.grade_color+'">'+d+'</span>';}},
      {data:'grade',render:function(d,t,row){return '<span class="grade-tag" style="background:'+row.grade_color+'">'+d+'</span>';}},
      {data:null,render:function(row){
        return '<a class="drv-link" data-id="'+row.id+'">'+esc(row.code)+'</a>'+
               (row.name?'<div class="text-muted small">'+esc(row.name)+'</div>':'');}},
      {data:'speeding',className:'text-end'},
      {data:'zone_viol',className:'text-end'},
      {data:'idling',className:'text-end'},
      {data:'idle_min',className:'text-end',render:fmtNum},
      {data:'events',className:'text-end',render:fmtNum},
      {data:'points',className:'text-end',render:ptsCell}
    ]
  });
  $('#driverTable tbody').on('click','.drv-link',function(){ openDriver($(this).data('id')); });
}

/* ===================== EVENTS ===================== */
var eventsTable;
function initEvents(){
  var p = new URLSearchParams(location.search);
  if(p.get('type')) $('#fType').val(p.get('type'));
  eventsTable = $('#eventTable').DataTable({
    serverSide:true, processing:true, pageLength:25,
    order:[[0,'desc']],
    ajax:{
      url:'api/events.php',
      data:function(d){ var f=filterParams(); d.type=f.type; d.from=f.from; d.to=f.to;
        if(p.get('driver_id')) d.driver_id=p.get('driver_id'); }
    },
    columns:[
      {data:null,render:function(r){return esc(r.date||'')+(r.time?' <span class="text-muted">'+r.time+'</span>':'');}},
      {data:null,render:function(r){return typeBadge(r.type_label,r.type_color);}},
      {data:'driver',render:esc},
      {data:'zone',render:function(d){return esc((d||'').substring(0,60));}},
      {data:'duration',className:'text-end',render:function(d){return d==null?'':fmtNum(d);}},
      {data:null,className:'text-end',render:function(r){
        if(r.type==='speeding') return r.metric? '<b>'+r.metric+'</b> km/h'+(r.metric2?' <span class="text-muted">avg '+r.metric2+'</span>':'') : '';
        if(r.type==='idling') return r.metric!=null? Math.round(r.metric*100)+'% idle' : '';
        return r.metric==null?'':fmtNum(r.metric);
      }},
      {data:'points',className:'text-end',render:ptsCell}
    ]
  });
}

/* ===================== DRIVER MODAL ===================== */
function openDriver(id){
  $.getJSON('api/drivers.php',{id:id},function(d){
    if(d.status!=='ok') return;
    $('#dmName').text(d.driver.code+(d.driver.name?' · '+d.driver.name:''));
    $('#dmCode').text('Tracker '+d.driver.code);
    $('#dmScore').text(d.score).css('background',d.grade_color);
    $('#dmGrade').text('Grade '+d.grade).css('background',d.grade_color);
    $('#dmEvents').text(fmtNum(d.events));
    $('#dmPoints').text(fmtNum(d.points));
    $('#dmTypes').text(d.breakdown.length);
    $('#dmViewEvents').attr('href','events.php?driver_id='+d.driver.id);
    $('#dmTable').html(d.breakdown.map(b=>
      '<tr><td>'+typeBadge(b.label,b.color)+'</td><td class="text-end">'+fmtNum(b.count)+
      '</td><td class="text-end">'+fmtNum(b.duration)+'</td><td class="text-end">'+ptsCell(b.points)+'</td></tr>').join(''));
    destroyChart('dm');
    charts.dm = new Chart(document.getElementById('dmChart'),{
      type:'bar',
      data:{labels:d.breakdown.map(b=>b.label),datasets:[{label:'Events',data:d.breakdown.map(b=>b.count),
        backgroundColor:d.breakdown.map(b=>b.color),borderRadius:5}]},
      options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
    });
    new bootstrap.Modal(document.getElementById('driverModal')).show();
  });
}

/* ===================== IMPORT ===================== */
function initImport(){
  var dz=$('#dropzone'), input=$('#fileInput');
  dz.on('click',()=>input.click());
  dz.on('dragover',e=>{e.preventDefault();dz.addClass('drag');});
  dz.on('dragleave',()=>dz.removeClass('drag'));
  dz.on('drop',function(e){e.preventDefault();dz.removeClass('drag');uploadFiles(e.originalEvent.dataTransfer.files);});
  input.on('change',function(){uploadFiles(this.files);});
  loadImports();
}
function uploadFiles(files){
  if(!files.length) return;
  var fd=new FormData();
  for(var i=0;i<files.length;i++) fd.append('files[]',files[i]);
  $('#uploadResults').html('<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> Parsing &amp; importing '+files.length+' file(s)…</div>');
  $.ajax({url:'api/upload.php',method:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
   .done(function(res){
     var html=(res.results||[]).map(function(r){
       var cls=r.status==='ok'?'r-ok':(r.status==='duplicate'?'r-dup':'r-err');
       var ic=r.status==='ok'?'bi-check-circle-fill':(r.status==='duplicate'?'bi-exclamation-circle-fill':'bi-x-circle-fill');
       var meta = r.status==='ok'
         ? '<div class="meta"><b>'+r.category+'</b><br>'+r.events+' events · '+r.drivers+' drivers'+(r.period?' · '+r.period:'')+'</div>'
         : '<div class="meta">'+esc(r.message||'')+'</div>';
       return '<div class="result-row '+cls+'"><i class="bi '+ic+'"></i><span>'+esc(r.file)+'</span>'+meta+'</div>';
     }).join('');
     $('#uploadResults').html(html);
     $('#postImport').removeClass('d-none');
     importTable.ajax.reload();
   })
   .fail(function(){ $('#uploadResults').html('<div class="text-danger">Upload failed. Check that XAMPP/MySQL is running.</div>'); });
}
var importTable;
function loadImports(){
  importTable=$('#importTable').DataTable({
    ajax:{url:'api/imports.php',dataSrc:'data'},
    order:[[5,'desc']],
    columns:[
      {data:'filename',render:function(d){return '<i class="bi bi-filetype-xlsx text-success"></i> '+esc(d);}},
      {data:null,render:function(r){return typeBadge(r.type_label,r.type_color);}},
      {data:'category',render:esc},
      {data:'period',render:esc},
      {data:'events',className:'text-end',render:fmtNum},
      {data:'imported',render:esc},
      {data:'id',orderable:false,className:'text-end',render:function(id){
        return '<button class="btn btn-sm btn-outline-danger del-import" data-id="'+id+'"><i class="bi bi-trash"></i></button>';}}
    ]
  });
  $('#importTable tbody').on('click','.del-import',function(){
    var id=$(this).data('id');
    if(!confirm('Delete this import and all its events?')) return;
    $.post('api/imports.php',{action:'delete',id:id},function(){ importTable.ajax.reload(); });
  });
}

/* ===================== USERS ===================== */
var userTable, userModal;
function rolePill(r){ return '<span class="role-pill role-'+r+'">'+r+'</span>'; }
function statusPill(s){ return '<span class="status-pill st-'+s+'">'+s+'</span>'; }
function initUsers(){
  userModal=new bootstrap.Modal(document.getElementById('userModal'));
  userTable=$('#userTable').DataTable({
    ajax:{url:'api/users.php',dataSrc:'data'},
    order:[[0,'asc']],
    columns:[
      {data:null,render:function(r){
        var ini=(r.name||'?').charAt(0).toUpperCase();
        return '<div class="d-flex align-items-center gap-2"><span class="avatar" style="width:30px;height:30px;font-size:13px;border-radius:8px">'+ini+'</span><b>'+esc(r.name)+'</b>'+(r.is_self?' <span class="text-muted small">(you)</span>':'')+'</div>';}},
      {data:'username',render:function(d){return '@'+esc(d);}},
      {data:'email',render:function(d){return esc(d)||'<span class="text-muted">—</span>';}},
      {data:'phone',render:function(d){return esc(d)||'<span class="text-muted">—</span>';}},
      {data:'role',render:rolePill},
      {data:'status',render:statusPill},
      {data:'last_login',render:function(d){return d?esc(d):'<span class="text-muted">never</span>';}},
      {data:null,orderable:false,className:'text-end',render:function(r){
        var del = r.is_self ? '' : '<button class="btn btn-sm btn-outline-danger u-del" data-id="'+r.id+'"><i class="bi bi-trash"></i></button>';
        return '<button class="btn btn-sm btn-outline-primary u-edit me-1" data-id="'+r.id+'"><i class="bi bi-pencil"></i></button>'+del;}}
    ]
  });

  $('#addUserBtn').on('click',function(){
    $('#userForm')[0].reset(); $('#umId').val(''); $('#umAction').val('create');
    $('#umTitle').text('Add User'); $('#umError').addClass('d-none');
    $('#umPassword').attr('required',true); $('#umPwHint').text('(min 6 chars)');
    userModal.show();
  });

  $('#userTable tbody').on('click','.u-edit',function(){
    var r=userTable.row($(this).closest('tr')).data();
    $('#umAction').val('update'); $('#umTitle').text('Edit User'); $('#umError').addClass('d-none');
    $('#umId').val(r.id); $('#umName').val(r.name); $('#umUsername').val(r.username);
    $('#umEmail').val(r.email); $('#umPhone').val(r.phone); $('#umRole').val(r.role); $('#umStatus').val(r.status);
    $('#umPassword').val('').attr('required',false); $('#umPwHint').text('(leave blank to keep current)');
    userModal.show();
  });

  $('#userTable tbody').on('click','.u-del',function(){
    var id=$(this).data('id');
    if(!confirm('Delete this user? This cannot be undone.')) return;
    $.post('api/users.php',{action:'delete',id:id},function(res){
      if(res.status==='ok') userTable.ajax.reload(); else alert(res.message||'Error');
    },'json').fail(xhrErr);
  });

  $('#userForm').on('submit',function(e){
    e.preventDefault();
    $.post('api/users.php',$(this).serialize(),function(res){
      if(res.status==='ok'){ userModal.hide(); userTable.ajax.reload(); }
      else { $('#umError').removeClass('d-none').text(res.message||'Error'); }
    },'json').fail(function(x){ $('#umError').removeClass('d-none').text(errMsg(x)); });
  });
}

/* ===================== PROFILE ===================== */
function initProfile(){
  $('#profileForm').on('submit',function(e){
    e.preventDefault();
    $.post('api/profile.php',$(this).serialize(),function(res){
      flash('#pfMsg',res.status==='ok','Profile updated.',res.message);
      if(res.status==='ok') setTimeout(()=>location.reload(),800);
    },'json').fail(function(x){ flash('#pfMsg',false,'',errMsg(x)); });
  });
  $('#passwordForm').on('submit',function(e){
    e.preventDefault();
    if($('[name=new_password]').val()!==$('#pwConfirm').val()){
      flash('#pwMsg',false,'','New passwords do not match.'); return;
    }
    $.post('api/profile.php',$(this).serialize(),function(res){
      flash('#pwMsg',res.status==='ok','Password updated.',res.message);
      if(res.status==='ok'){ $('#passwordForm')[0].reset(); }
    },'json').fail(function(x){ flash('#pwMsg',false,'',errMsg(x)); });
  });
}
function flash(sel,ok,okMsg,errMsg){
  $(sel).html('<div class="alert '+(ok?'alert-success':'alert-danger')+' py-2">'+esc(ok?okMsg:(errMsg||'Error'))+'</div>');
}

/* ===================== EMAIL REPORTS ===================== */
var recipModal;
function initEmail(){
  recipModal=new bootstrap.Modal(document.getElementById('recipModal'));
  loadEmailData();

  $('#settingsForm').on('submit',function(e){
    e.preventDefault();
    var data=$(this).serialize()+'&action=save_settings';
    $.post('api/email.php',data,function(res){
      flash('#settingsMsg',res.status==='ok','Settings saved.',res.message);
    },'json').fail(x=>flash('#settingsMsg',false,'',errMsg(x)));
  });

  $('#testBtn').on('click',function(){
    var to=$('#testEmail').val();
    if(!to){ flash('#settingsMsg',false,'','Enter a test email address.'); return; }
    var $b=$(this).prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.post('api/email.php',{action:'test',to:to},function(res){
      flash('#settingsMsg',res.status==='ok',res.message,res.message);
    },'json').fail(x=>flash('#settingsMsg',false,'',errMsg(x)))
     .always(()=>$b.prop('disabled',false).html('<i class="bi bi-envelope-check"></i> Send Test'));
  });

  $('#sendBtn').on('click',function(){
    var from=$('#sendFrom').val(), to=$('#sendTo').val();
    if(!from||!to){ flash('#sendMsg',false,'','Select both dates.'); return; }
    if(!confirm('Generate and email the violation report for '+from+' to '+to+' to all active recipients?')) return;
    var genNotices=$('#sendGenNotices').is(':checked')?'1':'0';
    var $b=$(this).prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span>');
    flash('#sendMsg',true,'Generating workbook and sending email…','');
    $.post('api/email.php',{action:'send',fromDate:from,toDate:to,gen_notices:genNotices},function(res){
      flash('#sendMsg',res.status==='ok',
        res.message+(res.drivers!=null?(' ('+res.drivers+' drivers, '+res.violations+' violations)'):''),res.message);
      loadEmailData();
    },'json').fail(x=>flash('#sendMsg',false,'',errMsg(x)))
     .always(()=>$b.prop('disabled',false).html('<i class="bi bi-send"></i>'));
  });

  $('#previewBtn').on('click',function(){
    var from=$('#sendFrom').val(), to=$('#sendTo').val();
    if(!from||!to){ flash('#sendMsg',false,'','Select both dates.'); return; }
    window.location='api/download_report.php?fromDate='+from+'&toDate='+to;
  });

  $('#addRecipientBtn').on('click',function(){
    $('#recipForm')[0].reset(); $('#rmError').addClass('d-none'); recipModal.show();
  });
  $('#recipForm').on('submit',function(e){
    e.preventDefault();
    $.post('api/email.php',$(this).serialize()+'&action=add_recipient',function(res){
      if(res.status==='ok'){ recipModal.hide(); loadEmailData(); }
      else $('#rmError').removeClass('d-none').text(res.message||'Error');
    },'json').fail(x=>$('#rmError').removeClass('d-none').text(errMsg(x)));
  });

  $('#recipTable tbody').on('click','.r-del',function(){
    if(!confirm('Remove this recipient?')) return;
    $.post('api/email.php',{action:'delete_recipient',id:$(this).data('id')},loadEmailData,'json');
  });
  $('#recipTable tbody').on('click','.r-toggle',function(){
    $.post('api/email.php',{action:'toggle_recipient',id:$(this).data('id')},loadEmailData,'json');
  });
}

function loadEmailData(){
  $.getJSON('api/email.php',function(d){
    var s=d.settings||{};
    $('#s_host').val(s.smtp_host||''); $('#s_port').val(s.smtp_port||'');
    $('#s_secure').val(s.smtp_secure||'tls'); $('#s_user').val(s.smtp_user||'');
    $('#s_femail').val(s.from_email||''); $('#s_fname').val(s.from_name||'');
    $('#s_subject').val(s.email_subject||''); $('#s_start').val(s.report_start_time||'');
    $('#s_end').val(s.report_end_time||'');
    $('#s_pwhint').text(s.smtp_pass_set?'(saved — leave blank to keep)':'(not set)');
    if(d.range){ if(!$('#sendFrom').val())$('#sendFrom').val(d.range.from); if(!$('#sendTo').val())$('#sendTo').val(d.range.to); }

    var typeBadgeColor={to:'#4f46e5',cc:'#0ea5e9',bcc:'#64748b'};
    $('#recipTable tbody').html((d.recipients||[]).map(function(r){
      var act=r.active==1;
      return '<tr><td><b>'+esc(r.email)+'</b></td><td>'+(esc(r.name)||'<span class="text-muted">—</span>')+
        '</td><td><span class="badge-type" style="background:'+typeBadgeColor[r.rtype]+'">'+r.rtype.toUpperCase()+'</span></td>'+
        '<td><span class="status-pill '+(act?'st-active':'st-inactive')+'">'+(act?'active':'inactive')+'</span></td>'+
        '<td class="text-end"><button class="btn btn-sm btn-outline-secondary r-toggle me-1" data-id="'+r.id+'">'+(act?'Disable':'Enable')+
        '</button><button class="btn btn-sm btn-outline-danger r-del" data-id="'+r.id+'"><i class="bi bi-trash"></i></button></td></tr>';
    }).join('') || '<tr><td colspan="5" class="text-center text-muted">No recipients yet.</td></tr>');

    $('#logTable tbody').html((d.log||[]).map(function(l){
      var ok=l.status==='sent';
      return '<tr><td>'+esc(l.sent_at)+'</td><td>'+esc((l.period_from||'')+' → '+(l.period_to||''))+
        '</td><td class="text-end">'+l.recipients+'</td>'+
        '<td><span class="status-pill '+(ok?'st-active':'st-inactive')+'">'+l.status+'</span></td>'+
        '<td style="max-width:340px">'+esc((l.message||'').substring(0,120))+'</td><td>'+esc(l.sent_by||'')+'</td></tr>';
    }).join('') || '<tr><td colspan="6" class="text-center text-muted">No emails sent yet.</td></tr>');
  });
}
function errMsg(xhr){ try{ return JSON.parse(xhr.responseText).message||'Request failed'; }catch(e){ return 'Request failed'; } }
function xhrErr(x){ alert(errMsg(x)); }

/* ===================== TRUCK MOVEMENT (Google Maps) ===================== */
var mvMap, mvDirRenderer, mvMarkers=[], mvPoly=null, mvGeocoder, geoCache={};
function loadGoogleMaps(){
  return new Promise(function(resolve,reject){
    if(window.google && google.maps){ resolve(); return; }
    if(!window.GMAPS_KEY){ reject('No Google Maps API key configured.'); return; }
    window.__gmapsCb=function(){ resolve(); };
    var s=document.createElement('script');
    s.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(GMAPS_KEY)+'&libraries=geometry&callback=__gmapsCb';
    s.async=true; s.onerror=function(){ reject('Failed to load Google Maps (check the API key / referrer restrictions).'); };
    document.head.appendChild(s);
  });
}
function initMovement(){
  $.getJSON('api/trips.php',{drivers:1},function(d){
    var o='<option value="">Select driver…</option>';
    (d.data||[]).forEach(function(r){ o+='<option value="'+r.id+'">'+esc(r.code)+(r.name?' · '+esc(r.name):'')+' ('+r.trips+' trips)</option>'; });
    $('#mvDriver').html(o);
  });
  $('#mvDriver').on('change',function(){
    var id=$(this).val(); $('#mvDate').html('<option value="">All dates</option>');
    if(!id) return;
    $.getJSON('api/trips.php',{driver_id:id,dates:1},function(d){
      var o='<option value="">All dates</option>';
      (d.data||[]).forEach(function(dt){ o+='<option value="'+dt+'">'+dt+'</option>'; });
      $('#mvDate').html(o);
    });
  });
  $('#mvShow').on('click',showMovement);
}
function showMovement(){
  var id=$('#mvDriver').val(); if(!id){ alert('Pick a driver first.'); return; }
  var date=$('#mvDate').val();
  var $btn=$('#mvShow').prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span> Loading…');
  loadGoogleMaps().then(function(){
    if(!mvMap){
      mvMap=new google.maps.Map(document.getElementById('map'),{center:{lat:7.31,lng:125.68},zoom:11,mapTypeControl:true,streetViewControl:false});
      mvGeocoder=new google.maps.Geocoder();
      mvDirRenderer=new google.maps.DirectionsRenderer({suppressMarkers:true,polylineOptions:{strokeColor:'#4f46e5',strokeWeight:5,strokeOpacity:.85}});
    }
    return $.getJSON('api/trips.php',{driver_id:id,date:date});
  }).then(function(res){ renderTrips(res); })
  .catch(function(e){ alert('Map error: '+(typeof e==='string'?e:'see console')); console.error(e); })
  .finally(function(){ $btn.prop('disabled',false).html('<i class="bi bi-geo-alt"></i> Show Movement'); });
}
// Bias geocoding to the Davao region so vague names don't land elsewhere.
var DAVAO_BOUNDS=null;
function davaoBounds(){
  if(!DAVAO_BOUNDS) DAVAO_BOUNDS=new google.maps.LatLngBounds({lat:6.0,lng:125.0},{lat:8.3,lng:126.8});
  return DAVAO_BOUNDS;
}
function geoQuery(place){
  var p=place.trim();
  if(/philippines/i.test(p)) return p;                 // already a full address — leave it
  if((p.match(/,/g)||[]).length>=2) return p+', Philippines';
  return p+', Davao del Norte, Philippines';            // bare name — add a regional hint
}
function inDavao(lat,lng){ return lat>=6.0&&lat<=8.3&&lng>=125.0&&lng<=126.8; }
function geocodeOne(place){
  return new Promise(function(resolve){
    if(!place){ resolve(null); return; }
    var key=place.trim().toLowerCase();
    if(geoCache[key]!==undefined){ resolve(geoCache[key]); return; }
    mvGeocoder.geocode({address:geoQuery(place),bounds:davaoBounds(),componentRestrictions:{country:'PH'}},function(r,status){
      var hit=null;
      if(status==='OK'&&r&&r.length){
        // Prefer the first result that actually falls inside the Davao region.
        var best=r.find(function(x){var l=x.geometry.location;return inDavao(l.lat(),l.lng());})||r[0];
        var l=best.geometry.location; hit={lat:l.lat(),lng:l.lng()};
      }
      geoCache[key]=hit;
      setTimeout(function(){ resolve(hit); },120); // gentle on the geocoder
    });
  });
}
async function resolveCoord(place,coord){ return coord ? coord : await geocodeOne(place); }
var mvLegRenderers=[], mvLegPolys=[], mvDirSvc=null, mvDirWarned=false;
function clearRoutes(){
  if(mvPoly){ mvPoly.setMap(null); mvPoly=null; }
  mvLegRenderers.forEach(function(r){ r.setMap(null); }); mvLegRenderers=[];
  mvLegPolys.forEach(function(p){ p.setMap(null); }); mvLegPolys=[];
}
function legPoly(a,b){
  var p=new google.maps.Polyline({path:[a,b],map:mvMap,strokeColor:'#4f46e5',strokeWeight:3,strokeOpacity:.55,
    icons:[{icon:{path:google.maps.SymbolPath.FORWARD_CLOSED_ARROW},offset:'55%'}]});
  mvLegPolys.push(p);
}
/** Route one trip leg along the road; fall back to a straight line for that leg only. */
function routeLeg(a,b){
  return new Promise(function(resolve){
    if(!mvDirSvc) mvDirSvc=new google.maps.DirectionsService();
    mvDirSvc.route({origin:a,destination:b,travelMode:google.maps.TravelMode.DRIVING},function(r,st){
      if(st==='OK'){
        var rend=new google.maps.DirectionsRenderer({map:mvMap,suppressMarkers:true,preserveViewport:true,
          polylineOptions:{strokeColor:'#4f46e5',strokeWeight:5,strokeOpacity:.8}});
        rend.setDirections(r); mvLegRenderers.push(rend);
      } else {
        legPoly(a,b); // straight fallback for this leg
        if((st==='REQUEST_DENIED'||st==='OVER_QUERY_LIMIT') && !mvDirWarned){
          mvDirWarned=true;
          $('#mvStats').append('<div class="text-muted small mt-2"><i class="bi bi-exclamation-triangle"></i> '+
            'Road routing unavailable ('+st+') — showing straight lines. Enable the <b>Directions API</b> for your Google Maps key.</div>');
        }
      }
      setTimeout(resolve,120); // gentle on the Directions quota
    });
  });
}
function renderTrips(res){
  mvMarkers.forEach(m=>m.setMap(null)); mvMarkers=[];
  clearRoutes();
  if(mvDirRenderer) mvDirRenderer.setMap(null);
  mvDirWarned=false;
  var trips=res.trips||[];
  var totalKm=trips.reduce((a,t)=>a+(+t.distance||0),0);
  var maxsp=trips.reduce((a,t)=>Math.max(a,+t.max_speed||0),0);
  var totMin=trips.reduce((a,t)=>a+(+t.duration_min||0),0);
  $('#mvStats').html('<div class="row g-2 text-center">'+
    miniStat('Trips',trips.length)+miniStat('Distance',totalKm.toFixed(1)+' km')+
    miniStat('Travel',Math.round(totMin)+' min')+miniStat('Max',maxsp+' km/h')+'</div>');
  if(!trips.length){ $('#mvList').html('<div class="empty"><i class="bi bi-truck"></i><p>No trips for this selection.</p></div>'); return; }
  $('#mvList').html(trips.map(function(t,i){
    return '<div class="trip-row"><div class="tr-no">'+(i+1)+'</div><div class="tr-body">'+
      '<div class="tr-time">'+t.start_time+' → '+t.end_time+' <span class="tr-km">'+(+t.distance).toFixed(1)+' km · '+t.max_speed+' km/h</span></div>'+
      '<div class="tr-place"><i class="bi bi-arrow-up-circle text-success"></i> '+esc(t.start_clean||t.start_place)+'</div>'+
      '<div class="tr-place"><i class="bi bi-arrow-down-circle text-danger"></i> '+esc(t.end_clean||t.end_place)+'</div>'+
      '</div></div>';
  }).join(''));

  (async function(){
    var bounds=new google.maps.LatLngBounds(), missed=0;
    // resolve start/end coords for each trip
    for(var i=0;i<trips.length;i++){
      var t=trips[i];
      t._sc=await resolveCoord(t.start_clean||t.start_place,t.start_coord);
      t._ec=await resolveCoord(t.end_clean||t.end_place,t.end_coord);
      if(!t._sc) missed++;
      if(!t._ec) missed++;
    }
    // build the ordered sequence of stop points (dedupe consecutive identical)
    var pts=[];
    trips.forEach(function(t){
      if(t._sc) pts.push({pos:t._sc,trip:t,kind:'start'});
      if(t._ec) pts.push({pos:t._ec,trip:t,kind:'end'});
    });
    var seq=[];
    pts.forEach(function(p){ var l=seq[seq.length-1];
      if(l&&Math.abs(l.pos.lat-p.pos.lat)<1e-6&&Math.abs(l.pos.lng-p.pos.lng)<1e-6) return; seq.push(p); });
    if(!seq.length){ alert('Could not map any of these locations (names may not be geocodable).'); return; }
    seq.forEach(function(p,n){
      var color=(n===0?'#10b981':(n===seq.length-1?'#ef4444':'#4f46e5'));
      var m=new google.maps.Marker({position:p.pos,map:mvMap,label:{text:String(n+1),color:'#fff',fontSize:'11px',fontWeight:'700'},
        icon:{path:google.maps.SymbolPath.CIRCLE,scale:12,fillColor:color,fillOpacity:1,strokeColor:'#fff',strokeWeight:2}});
      var pl=p.kind==='start'?p.trip.start_place:p.trip.end_place;
      var tm=p.kind==='start'?p.trip.start_time:p.trip.end_time;
      var iw=new google.maps.InfoWindow({content:'<div style="font-size:13px"><b>'+(p.kind==='start'?'Departed':'Arrived')+' '+tm+'</b><br>'+esc(pl)+'</div>'});
      m.addListener('click',function(){ iw.open(mvMap,m); });
      mvMarkers.push(m); bounds.extend(p.pos);
    });
    mvMap.fitBounds(bounds);
    // Draw each trip leg along the road (per-leg avoids the 25-waypoint limit + zigzag).
    var snap=$('#mvSnap').is(':checked');
    for(var k=0;k<trips.length;k++){
      var tt=trips[k];
      if(!tt._sc || !tt._ec) continue;
      if(Math.abs(tt._sc.lat-tt._ec.lat)<1e-6 && Math.abs(tt._sc.lng-tt._ec.lng)<1e-6) continue; // same point
      if(snap){ await routeLeg(tt._sc,tt._ec); } else { legPoly(tt._sc,tt._ec); }
    }
    if(missed){ $('#mvStats').append('<div class="text-muted small mt-2"><i class="bi bi-info-circle"></i> '+missed+' point(s) could not be geocoded.</div>'); }
  })();
}
function miniStat(label,val){
  return '<div class="col"><div class="mini-stat"><div class="ms-val">'+val+'</div><div class="ms-lbl">'+label+'</div></div></div>';
}

/* ===================== INCIDENT REPORTS ===================== */
var incTable, incModal, incViewModal, incEditMap, incMarker, incGeocoder;
var SEV={low:'#22c55e',medium:'#f59e0b',high:'#f97316',critical:'#ef4444'};
var INC_STATUS={open:'#ef4444',investigating:'#f59e0b',resolved:'#22c55e',closed:'#64748b'};
function sevBadge(s){ return '<span class="badge-type" style="background:'+(SEV[s]||'#94a3b8')+'">'+s+'</span>'; }
function statBadge(s){ return '<span class="badge-type" style="background:'+(INC_STATUS[s]||'#94a3b8')+'">'+s+'</span>'; }

function initIncidents(){
  incModal=new bootstrap.Modal(document.getElementById('incModal'));
  incViewModal=new bootstrap.Modal(document.getElementById('incViewModal'));
  // driver dropdown
  $.getJSON('api/drivers.php',function(d){
    var o='<option value="">—</option>';
    (d.data||[]).forEach(r=>o+='<option value="'+r.id+'">'+esc(r.code)+(r.name?' · '+esc(r.name):'')+'</option>');
    $('#incDriver').html(o);
  });
  incTable=$('#incTable').DataTable({
    ajax:{url:'api/incidents.php',dataSrc:'data'}, order:[[0,'desc']],
    columns:[
      {data:'incident_date'},
      {data:null,render:function(r){return '<b>'+esc(r.truck_code||'—')+'</b>'+(r.driver_code?'<div class="text-muted small">'+esc(r.driver_code)+'</div>':'');}},
      {data:'itype',render:esc},
      {data:'severity',render:sevBadge},
      {data:null,render:function(r){return esc((r.address||'').substring(0,40))||(r.lat?('('+(+r.lat).toFixed(4)+', '+(+r.lng).toFixed(4)+')'):'<span class="text-muted">—</span>');}},
      {data:'status',render:statBadge},
      {data:null,orderable:false,className:'text-end',render:function(r){
        var b='<button class="btn btn-sm btn-outline-secondary i-view me-1" data-id="'+r.id+'"><i class="bi bi-eye"></i></button>';
        if(window.CAN_MANAGE) b+='<button class="btn btn-sm btn-outline-primary i-edit me-1" data-id="'+r.id+'"><i class="bi bi-pencil"></i></button>'+
          '<button class="btn btn-sm btn-outline-danger i-del" data-id="'+r.id+'"><i class="bi bi-trash"></i></button>';
        return b;}}
    ]
  });

  $('#addIncidentBtn').on('click',function(){
    $('#incForm')[0].reset(); $('#incId').val(''); $('#incLat').val(''); $('#incLng').val('');
    $('#incError').addClass('d-none'); $('#incTitle').text('Report Incident');
    incModal.show();
  });

  document.getElementById('incModal').addEventListener('shown.bs.modal',function(){ setupIncidentMap(); });

  $('#incTable tbody').on('click','.i-edit',function(){ openIncidentEdit($(this).data('id')); });
  $('#incTable tbody').on('click','.i-view',function(){ openIncidentView($(this).data('id')); });
  $('#incTable tbody').on('click','.i-del',function(){
    if(!confirm('Delete this incident?')) return;
    $.post('api/incidents.php',{action:'delete',id:$(this).data('id')},function(){ incTable.ajax.reload(); },'json');
  });

  $('#incForm').on('submit',function(e){
    e.preventDefault();
    $.ajax({url:'api/incidents.php',method:'POST',data:new FormData(this),processData:false,contentType:false,dataType:'json'})
     .done(function(res){ if(res.status==='ok'){ incModal.hide(); incTable.ajax.reload(); }
        else $('#incError').removeClass('d-none').text(res.message||'Error'); })
     .fail(x=>$('#incError').removeClass('d-none').text(errMsg(x)));
  });
}

function setupIncidentMap(latlng){
  loadGoogleMaps().then(function(){
    var center=latlng||{lat:7.31,lng:125.68};
    if(!incEditMap){
      incEditMap=new google.maps.Map(document.getElementById('incEditMap'),{center:center,zoom:12,streetViewControl:false,mapTypeControl:false});
      incGeocoder=new google.maps.Geocoder();
      incEditMap.addListener('click',function(e){ setIncMarker(e.latLng,true); });
    }
    google.maps.event.trigger(incEditMap,'resize');
    incEditMap.setCenter(center);
    if(latlng){ setIncMarker(new google.maps.LatLng(latlng.lat,latlng.lng),false); }
  }).catch(function(e){ $('#incError').removeClass('d-none').text('Map: '+e); });
}
function setIncMarker(latLng,reverse){
  if(!incMarker){ incMarker=new google.maps.Marker({map:incEditMap,draggable:true});
    incMarker.addListener('dragend',function(e){ writeIncLatLng(e.latLng,true); }); }
  incMarker.setPosition(latLng); incEditMap.panTo(latLng);
  writeIncLatLng(latLng,reverse);
}
function writeIncLatLng(latLng,reverse){
  $('#incLat').val(latLng.lat().toFixed(7)); $('#incLng').val(latLng.lng().toFixed(7));
  if(reverse && incGeocoder){ incGeocoder.geocode({location:latLng},function(r,s){ if(s==='OK'&&r[0]&&!$('#incAddress').val()) $('#incAddress').val(r[0].formatted_address); }); }
}

function openIncidentEdit(id){
  $.getJSON('api/incidents.php',{id:id},function(res){
    if(res.status!=='ok') return; var r=res.data;
    $('#incForm')[0].reset(); $('#incError').addClass('d-none'); $('#incTitle').text('Edit Incident #'+r.id);
    $('#incId').val(r.id); $('#incDate').val((r.incident_date||'').replace(' ','T').substring(0,16));
    $('#incTruck').val(r.truck_code); $('#incDriver').val(r.driver_id||''); $('#incType').val(r.itype);
    $('#incSeverity').val(r.severity); $('#incStatus').val(r.status); $('#incDesc').val(r.description);
    $('#incAddress').val(r.address); $('#incLat').val(r.lat||''); $('#incLng').val(r.lng||'');
    incModal.show();
    if(r.lat&&r.lng){ document.getElementById('incModal').addEventListener('shown.bs.modal',function h(){ setupIncidentMap({lat:+r.lat,lng:+r.lng}); document.getElementById('incModal').removeEventListener('shown.bs.modal',h); }); }
  });
}
function openIncidentView(id){
  $.getJSON('api/incidents.php',{id:id},function(res){
    if(res.status!=='ok') return; var r=res.data;
    $('#ivId').text(r.id); $('#ivPrint').attr('href','incident_print.php?id='+r.id);
    var photo=r.photo?'<img class="incident-photo mt-2" src="uploads/'+esc(r.photo)+'">':'';
    var loc=r.address?esc(r.address):''; if(r.lat) loc+=' <span class="text-muted">('+(+r.lat).toFixed(5)+', '+(+r.lng).toFixed(5)+')</span>';
    $('#ivBody').html('<div class="row g-3">'+
      '<div class="col-md-6"><div class="text-muted small">Date &amp; Time</div><b>'+esc(r.incident_date)+'</b></div>'+
      '<div class="col-md-6"><div class="text-muted small">Truck / Driver</div><b>'+esc(r.truck_code||'—')+'</b> '+(r.driver_code?esc(r.driver_code+' '+(r.driver_name||'')):'')+'</div>'+
      '<div class="col-md-4"><div class="text-muted small">Type</div>'+esc(r.itype)+'</div>'+
      '<div class="col-md-4"><div class="text-muted small">Severity</div>'+sevBadge(r.severity)+'</div>'+
      '<div class="col-md-4"><div class="text-muted small">Status</div>'+statBadge(r.status)+'</div>'+
      '<div class="col-12"><div class="text-muted small">Location</div>'+(loc||'—')+'</div>'+
      '<div class="col-12"><div class="text-muted small">Description</div>'+(esc(r.description)||'—')+'</div>'+
      (photo?'<div class="col-12">'+photo+'</div>':'')+
      '<div class="col-12 text-muted small">Reported by '+esc(r.reported_by||'—')+'</div>'+
    '</div>');
    incViewModal.show();
  });
}

/* ===================== VIOLATION NOTICES ===================== */
var noticeTable, genModal, statusModal;
var NOTICE_STATUS={pending:'#ef4444',scheduled:'#f59e0b',completed:'#22c55e',no_show:'#64748b'};
function noticeStatusBadge(s){ var lbl={pending:'Pending',scheduled:'Scheduled',completed:'Completed',no_show:'No-Show'};
  return '<span class="badge-type" style="background:'+(NOTICE_STATUS[s]||'#94a3b8')+'">'+(lbl[s]||s)+'</span>'; }
function vchip(n,label,color){ return n>0 ? '<span class="vchip" style="background:'+color+'">'+label+' '+n+'</span>' : ''; }
function initNotices(){
  genModal=new bootstrap.Modal(document.getElementById('genModal'));
  statusModal=new bootstrap.Modal(document.getElementById('statusModal'));
  loadNotices();

  $('#genNoticesBtn') && $('#genNoticesBtn').on('click',function(){ $('#genForm')[0].reset();
    $('#genMsg').empty(); $('#genForm [name=counseling_time]').val('8:00 AM and 1:00 PM');
    $('#genForm [name=counseling_venue]').val('PTSI Conference Room'); $('#genForm [name=issued_by]').val('Admin and Support');
    $('#genCDate').val(nextMondayStr()); // counseling is always a Monday
    genModal.show(); });

  $('#genForm').on('submit',function(e){ e.preventDefault();
    var $b=$(this).find('button[type=submit]').prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.post('api/notices.php',$(this).serialize()+'&action=generate',function(res){
      flash('#genMsg',res.status==='ok',res.message,res.message);
      if(res.status==='ok'){ loadNotices(); setTimeout(()=>genModal.hide(),1200); }
    },'json').fail(x=>flash('#genMsg',false,'',errMsg(x)))
     .always(()=>$b.prop('disabled',false).html('<i class="bi bi-magic"></i> Generate'));
  });

  $('#statusForm').on('submit',function(e){ e.preventDefault();
    $.post('api/notices.php',$(this).serialize()+'&action=update_status',function(res){
      if(res.status==='ok'){ statusModal.hide(); loadNotices(); } else alert(res.message||'Error');
    },'json').fail(xhrErr);
  });

  $('#printBatchBtn').on('click',function(){
    var params=[];
    var st=$('#printStatus').val(); if(st) params.push('status='+encodeURIComponent(st));
    var f=$('#printFrom').val();    if(f)  params.push('from='+f);
    var t=$('#printTo').val();      if(t)  params.push('to='+t);
    window.open('notice_print.php'+(params.length?'?'+params.join('&'):''),'_blank');
  });

  $('#noticeTable tbody').on('click','.n-status',function(){
    var r=noticeTable.row($(this).closest('tr')).data();
    $('#stId').val(r.id); $('#stStatus').val(r.status); $('#stRemarks').val('');
    $('#stDriver').text(r.control_no+' · '+r.driver_name+' ('+r.unit_no+')');
    statusModal.show();
  });
  $('#noticeTable tbody').on('click','.n-del',function(){
    if(!confirm('Delete this notice?')) return;
    $.post('api/notices.php',{action:'delete',id:$(this).data('id')},function(){ loadNotices(); },'json');
  });
  $('#noticeTable tbody').on('click','.n-done',function(){
    var id=$(this).data('id');
    $.post('api/notices.php',{action:'update_status',id:id,status:'completed'},function(){ loadNotices(); },'json');
  });
}
function loadNotices(){
  $.getJSON('api/notices.php',function(d){
    var s=d.summary||{};
    $('#nkTotal').text(s.total||0); $('#nkPending').text(s.pending||0);
    $('#nkScheduled').text(s.scheduled||0); $('#nkCompleted').text(s.completed||0); $('#nkNoShow').text(s.no_show||0);
    if(noticeTable){ noticeTable.clear().rows.add(d.data).draw(); return; }
    noticeTable=$('#noticeTable').DataTable({
      data:d.data, order:[[0,'desc']],
      columns:[
        {data:'control_no',render:function(d){return '<b>'+esc(d)+'</b>';}},
        {data:'driver_name',render:esc},
        {data:'unit_no',render:esc},
        {data:null,render:function(r){return esc((r.period_from||'')===(r.period_to||'')?r.period_from:(r.period_from+' → '+r.period_to));}},
        {data:null,render:function(r){
          return '<b>'+r.total+'</b> '+
            vchip(r.c_speeding,'Spd','#ef4444')+vchip(r.c_restricted,'Res','#dc2626')+
            vchip(r.c_no_parking,'NP','#f97316')+vchip(r.c_overstay,'OS','#f59e0b')+vchip(r.c_idle,'Idl','#eab308');}},
        {data:null,render:function(r){return r.counseling_date?esc(r.counseling_date)+'<div class="text-muted small">'+esc(r.counseling_time||'')+'</div>':'<span class="text-muted">—</span>';}},
        {data:'status',render:noticeStatusBadge},
        {data:null,orderable:false,className:'text-end',render:function(r){
          var b='<a href="notice_print.php?id='+r.id+'" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Print"><i class="bi bi-printer"></i></a>';
          if(window.CAN_MANAGE){
            if(r.status!=='completed') b+='<button class="btn btn-sm btn-outline-success n-done me-1" data-id="'+r.id+'" title="Mark completed"><i class="bi bi-check2"></i></button>';
            b+='<button class="btn btn-sm btn-outline-primary n-status me-1" title="Update status"><i class="bi bi-pencil"></i></button>';
            b+='<button class="btn btn-sm btn-outline-danger n-del" data-id="'+r.id+'" title="Delete"><i class="bi bi-trash"></i></button>';
          }
          return b;}}
      ]
    });
  });
}

/* ===================== VIOLATIONS MAP ===================== */
var mpMap, mpMarkers=[], mpHeat=null, mpInfo;
function initMapView(){
  // populate driver / truck filter
  $.getJSON('api/drivers.php',function(d){
    var o='<option value="">All drivers</option>';
    (d.data||[]).forEach(function(r){ o+='<option value="'+r.id+'">'+esc(r.code)+(r.name?' · '+esc(r.name):'')+'</option>'; });
    $('#mpDriver').html(o);
  });
  $('#mpApply').on('click',loadMapPoints);
  $('#mpDriver').on('change',loadMapPoints);
  $('input[name=mpView]').on('change',function(){ if(mpLastData) drawMapPoints(mpLastData); });
  loadMapPoints();
}
var mpLastData=null;
function loadMapPoints(){
  var $b=$('#mpApply').prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span>');
  loadGoogleMaps().then(function(){
    if(!mpMap){
      mpMap=new google.maps.Map(document.getElementById('mapView'),{center:{lat:7.4,lng:125.7},zoom:10,mapTypeControl:true,streetViewControl:false});
      mpInfo=new google.maps.InfoWindow();
    }
    return $.getJSON('api/map_points.php',{type:$('#mpType').val(),driver_id:$('#mpDriver').val(),from:$('#mpFrom').val(),to:$('#mpTo').val()});
  }).then(function(res){ mpLastData=res; drawMapPoints(res); })
  .catch(function(e){ alert('Map error: '+(typeof e==='string'?e:'see console')); console.error(e); })
  .finally(function(){ $b.prop('disabled',false).html('<i class="bi bi-funnel"></i> Apply'); });
}
function clearMapLayers(){
  mpMarkers.forEach(m=>m.setMap(null)); mpMarkers=[];
  if(mpHeat){ mpHeat.setMap(null); mpHeat=null; }
}
// Geocoder for the map (reuses the movement page's geoQuery/davaoBounds/inDavao/geoCache).
var mpGeo;
function mapGeocode(place){
  return new Promise(function(resolve){
    if(!place){ resolve(null); return; }
    var key=place.trim().toLowerCase();
    if(geoCache[key]!==undefined){ resolve(geoCache[key]); return; }
    if(!mpGeo) mpGeo=new google.maps.Geocoder();
    mpGeo.geocode({address:geoQuery(place),bounds:davaoBounds(),componentRestrictions:{country:'PH'}},function(r,st){
      var hit=null;
      if(st==='OK'&&r&&r.length){ var best=r.find(function(x){var l=x.geometry.location;return inDavao(l.lat(),l.lng());})||r[0];
        var l=best.geometry.location; hit={lat:l.lat(),lng:l.lng()}; }
      geoCache[key]=hit; setTimeout(function(){ resolve(hit); },110);
    });
  });
}
async function drawMapPoints(res){
  clearMapLayers();
  var pts=(res.data||[]).slice();
  // legend from counts
  $('#mpLegend').html(Object.keys(res.counts||{}).map(function(t){
    return '<span><i style="background:'+metaColor(t)+'"></i>'+esc(metaLabel(t))+' ('+res.counts[t]+')</span>';
  }).join('') || '<span class="text-muted">No events for this filter.</span>');
  if(!pts.length){ $('#mpBadge').html('<i class="bi bi-geo-alt-fill"></i> 0 events on map'); return; }

  // Geocode address-only events (e.g. speeding from the Speed Violation report) so they appear too.
  var needGeo=pts.filter(function(p){ return (p.lat==null||p.lng==null) && p.geo; });
  if(needGeo.length){
    var cap=Math.min(needGeo.length,250);
    $('#mpBadge').html('<span class="spinner-border spinner-border-sm"></span> Locating '+cap+' address-only event(s)…');
    for(var i=0;i<cap;i++){
      var c=await mapGeocode(needGeo[i].geo);
      if(c){ needGeo[i].lat=c.lat; needGeo[i].lng=c.lng; }
    }
  }
  var plot=pts.filter(function(p){ return p.lat!=null && p.lng!=null; });
  $('#mpBadge').html('<i class="bi bi-geo-alt-fill"></i> '+plot.length+' event'+(plot.length===1?'':'s')+' on map');
  if(!plot.length){ return; }

  var bounds=new google.maps.LatLngBounds();
  var view=$('input[name=mpView]:checked').val();

  if(view==='heat'){
    plot.forEach(function(p){
      var c=new google.maps.Circle({center:{lat:p.lat,lng:p.lng},radius:350,map:mpMap,
        strokeWeight:0,fillColor:p.color,fillOpacity:.16,clickable:false});
      mpMarkers.push(c); bounds.extend({lat:p.lat,lng:p.lng});
    });
  } else {
    var cap2=Math.min(plot.length,1500);
    for(var j=0;j<cap2;j++){
      var p=plot[j];
      var m=new google.maps.Marker({position:{lat:p.lat,lng:p.lng},map:mpMap,
        icon:{path:google.maps.SymbolPath.CIRCLE,scale:6,fillColor:p.color,fillOpacity:.85,strokeColor:'#fff',strokeWeight:1}});
      (function(p,m){ m.addListener('click',function(){
        mpInfo.setContent('<div style="font-size:13px"><b>'+esc(p.label)+'</b><br>'+esc(p.driver)+
          '<br>'+esc((p.date||'')+' '+(p.time||''))+'<br>'+esc((p.zone||'').substring(0,50))+
          (p.metric!=null?'<br>Metric: '+p.metric:'')+'</div>');
        mpInfo.setPosition({lat:p.lat,lng:p.lng}); mpInfo.open(mpMap);
      }); })(p,m);
      mpMarkers.push(m); bounds.extend({lat:p.lat,lng:p.lng});
    }
    if(plot.length>cap2){ $('#mpBadge').append(' · showing first '+cap2); }
  }
  fitMapBounds(bounds);
}
function fitMapBounds(bounds){
  // The map div may not be sized yet on first paint; re-fit once it's idle.
  google.maps.event.trigger(mpMap,'resize');
  mpMap.fitBounds(bounds);
  google.maps.event.addListenerOnce(mpMap,'idle',function(){
    if(mpMap.getZoom()<5){ google.maps.event.trigger(mpMap,'resize'); mpMap.fitBounds(bounds); }
  });
}
function metaColor(t){ var map={speeding:'#ef4444',idling:'#eab308',harsh_driving:'#db2777',seatbelt:'#e11d48',exception:'#7c3aed',
  restricted_zone:'#dc2626',no_parking:'#f97316',overstaying:'#f59e0b',geofence_visit:'#3b82f6',hot_spot:'#14b8a6',poi_visit:'#10b981',stop:'#64748b',trip:'#0ea5e9'};
  return map[t]||'#94a3b8'; }
function metaLabel(t){ var map={speeding:'Speeding',idling:'Idling',harsh_driving:'Harsh Driving',seatbelt:'Seat Belt',exception:'Exception',
  restricted_zone:'Restricted Zone',no_parking:'No-Parking',overstaying:'Overstaying',geofence_visit:'Geofence',hot_spot:'Hot Spot',poi_visit:'POI Visit',stop:'Stop',trip:'Trip'};
  return map[t]||t; }

/* ===================== REPORT BUILDER ===================== */
var rbTable;
function initReports(){
  $.getJSON('api/drivers.php',function(d){
    var o='<option value="">All drivers</option>';
    (d.data||[]).forEach(r=>o+='<option value="'+r.id+'">'+esc(r.code)+(r.name?' · '+esc(r.name):'')+'</option>');
    $('#rbDriver').html(o);
  });
  rbTable=$('#rbTable').DataTable({
    serverSide:true, processing:true, pageLength:25, order:[[0,'desc']],
    ajax:{ url:'api/events.php', data:function(d){ d.type=$('#rbType').val(); d.from=$('#rbFrom').val(); d.to=$('#rbTo').val(); d.driver_id=$('#rbDriver').val(); } },
    columns:[
      {data:null,render:function(r){return esc(r.date||'')+(r.time?' <span class="text-muted">'+r.time+'</span>':'');}},
      {data:null,render:function(r){return typeBadge(r.type_label,r.type_color);}},
      {data:'driver',render:esc},
      {data:'zone',render:function(d){return esc((d||'').substring(0,55));}},
      {data:'duration',className:'text-end',render:function(d){return d==null?'':fmtNum(d);}},
      {data:'metric',className:'text-end',render:function(d){return d==null?'':fmtNum(d);}},
      {data:'points',className:'text-end',render:ptsCell}
    ]
  });
  $('#rbApply').on('click',()=>rbTable.ajax.reload());
  $('#rbExport').on('click',function(){
    var p=$.param({type:$('#rbType').val(),from:$('#rbFrom').val(),to:$('#rbTo').val(),driver_id:$('#rbDriver').val()});
    window.location='api/export_events.php?'+p;
  });
  $('#rbPrint').on('click',function(){ window.print(); });
}
