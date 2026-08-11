(function(){
'use strict';
var path=location.pathname.replace(/\/+$/,'');
if(/\/admin\/scoring(?:\/index\.php)?$/.test(path)){
 var warning=[].find.call(document.querySelectorAll('.alert-warning'),function(el){return el.textContent.indexOf('secure token is not visible')>=0;});
 if(warning&&!document.getElementById('bdcDeskLinkForm')){
  var round=(new URLSearchParams(location.search)).get('round_id');
  var csrf=document.querySelector('input[name="_csrf"]');
  if(round&&csrf){var f=document.createElement('form');f.id='bdcDeskLinkForm';f.method='post';f.action='registration-link.php';f.className='mt-2';f.innerHTML='<input type="hidden" name="_csrf" value="'+csrf.value.replace(/"/g,'&quot;')+'"><input type="hidden" name="round_id" value="'+round+'"><button class="btn btn-primary">Generate New Registration Desk Link</button><div class="small text-muted mt-2">Only the secure URL changes. Existing competitors, bibs, check-ins and ready status are preserved.</div>';warning.appendChild(f);}
 }
}
if(/\/registration-desk(?:\/index\.php)?$/.test(path)){
 var toggle=document.getElementById('toggleProvisional');if(toggle)toggle.textContent='+ Add New BDC Competitor';
 var add=document.getElementById('addForm');if(add){var bib=add.querySelector('[name="bib"]');if(bib){bib.required=false;bib.placeholder='Optional · assign later';}var reason=add.querySelector('[name="override_reason"]');if(reason){reason.required=false;var holder=reason.closest('.col-12,.col-md-12,.mb-2,.mb-3');if(holder)holder.style.display='none';}}
 var title=[].find.call(document.querySelectorAll('h2'),function(h){return h.textContent.indexOf('Add competitor')>=0;});if(title)title.textContent='Add competitor';
}
})();