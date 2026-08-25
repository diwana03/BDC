(()=>{
  'use strict';
  if(document.querySelector('[data-dc-manual]'))document.querySelectorAll('a[href^="automation.php?id="]').forEach(link=>link.remove());
  if(document.querySelector('[data-dc-automatic]'))document.querySelectorAll('a[href^="category.php?id="]').forEach(link=>{link.href=link.href.replace('category.php','automatic-setup.php');link.textContent=link.textContent.replace('Category Workspace','Automatic Setup')});
})();
