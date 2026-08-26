(function(){
 'use strict';
 function enhanceTables(){
  document.querySelectorAll('table[data-mobile-cards]').forEach(function(table){
   var labels=[].map.call(table.querySelectorAll('thead th'),function(th){return th.textContent.trim()||'Actions';});
   table.querySelectorAll('tbody tr').forEach(function(row){
    [].forEach.call(row.children,function(cell,index){if(!cell.hasAttribute('data-label'))cell.setAttribute('data-label',labels[index]||'Details');});
   });
  });
 }
 function enhanceDashboard(){
  var topbar=document.querySelector('.admin-topbar-v203'),sidebar=document.querySelector('.admin-sidebar-v203');
  if(!topbar||!sidebar)return;
  var button=document.querySelector('.admin-mobile-menu-v428');
  if(!button){button=document.createElement('button');button.type='button';button.className='admin-mobile-menu-v428';button.setAttribute('aria-expanded','false');button.setAttribute('aria-controls','bdc-admin-navigation');button.innerHTML='<span aria-hidden="true">☰</span> Menu';topbar.appendChild(button);}
  sidebar.id=sidebar.id||'bdc-admin-navigation';
  var backdrop=document.querySelector('.admin-mobile-backdrop-v428');
  if(!backdrop){backdrop=document.createElement('button');backdrop.type='button';backdrop.className='admin-mobile-backdrop-v428';backdrop.setAttribute('aria-label','Close navigation');document.body.appendChild(backdrop);}
  function setOpen(open){document.body.classList.toggle('admin-mobile-nav-open',open);button.setAttribute('aria-expanded',open?'true':'false');}
  button.addEventListener('click',function(){setOpen(!document.body.classList.contains('admin-mobile-nav-open'));});backdrop.addEventListener('click',function(){setOpen(false);});
  sidebar.addEventListener('click',function(event){if(event.target.closest('a'))setOpen(false);});
  document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false);});
  matchMedia('(min-width:851px)').addEventListener('change',function(event){if(event.matches)setOpen(false);});
 }
 function init(){enhanceTables();enhanceDashboard();}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
