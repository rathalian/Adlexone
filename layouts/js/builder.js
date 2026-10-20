(function(){
var root=document.querySelector("[data-builder]");
if(!root)return;
var data=JSON.parse(root.getAttribute("data-builder")||"{}");
var form=document.getElementById("builder-form");
if(!form)return;
var nameEl=document.getElementById("builder-name");
var slugEl=document.getElementById("builder-slug");
var hintEl=document.getElementById("builder-hint");
var typeEl=document.getElementById("builder-type");
var slugDirty=slugEl&&slugEl.value.trim()!=="";
var typeDirty=typeEl&&typeEl.value.trim()!=="";
function slugify(v){return String(v||"").toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");}
function paint(sel){root.querySelectorAll(sel).forEach(function(el){var input=el.querySelector("input");el.classList.toggle("is-selected",!!(input&&input.checked));});}
function chosenBlueprint(){var input=form.querySelector("input[name=blueprint]:checked");return input?input.value:"matters";}
function text(id,value){var el=document.getElementById(id);if(el)el.textContent=value;}
function render(){
paint(".builder__blueprint");paint(".builder__icon");paint(".builder__choice");
var bp=data.blueprints&&data.blueprints[chosenBlueprint()]||{hint:"",screens:[],fields:[],rule:""};
var name=(nameEl&&nameEl.value.trim())||"";
if(typeEl){typeEl.placeholder=bp.record||"Job";}
var slug=(slugEl&&slugEl.value.trim())||"";
var hint=(hintEl&&hintEl.value.trim())||bp.hint||"";
var type=(typeEl&&typeEl.value.trim())||bp.record||(name?name+" record":"Record");
var address=slugify(slug||name)||"complaints";
text("builder-preview-name",name||"New application");
text("builder-preview-hint",hint);
text("builder-preview-url","?application="+address);
text("builder-preview-type",type);
var icon=form.querySelector("input[name=icon]:checked");
var use=document.querySelector("#builder-preview-icon use");
if(use&&icon&&data.sprite){use.setAttribute("href",data.sprite+"#"+icon.value);}
var tabs=document.getElementById("builder-preview-tabs");
if(tabs){tabs.textContent="";form.querySelectorAll("input[name=\"screens[]\"]:checked").forEach(function(input){
var label=input.closest("label");var nameNode=label&&label.querySelector(".builder__choice-label");
var tab=document.createElement("span");tab.className="builder__tab";tab.textContent=nameNode?nameNode.textContent:"Screen";tabs.appendChild(tab);});
if(!tabs.childNodes.length){var fallback=document.createElement("span");fallback.className="builder__tab";fallback.textContent="Work";tabs.appendChild(fallback);}}
var fields=document.getElementById("builder-preview-fields");
if(fields){fields.textContent="";
if(!bp.fields||!bp.fields.length){var empty=document.createElement("li");empty.textContent="You will add fields after the application exists.";fields.appendChild(empty);}
else{bp.fields.forEach(function(field){var li=document.createElement("li");var strong=document.createElement("span");strong.className="builder__field-name";strong.textContent=field.name;li.appendChild(strong);
if(field.values&&field.values.length){var vals=document.createElement("span");vals.className="builder__field-values";vals.textContent=field.values.join(", ");li.appendChild(vals);}fields.appendChild(li);});}}
var ruleRow=document.getElementById("builder-preview-rule-row");
if(bp.rule){if(!ruleRow){var facts=root.querySelector(".builder__facts");var access=document.getElementById("builder-preview-access");
ruleRow=document.createElement("div");ruleRow.id="builder-preview-rule-row";ruleRow.innerHTML="<dt>Rule</dt><dd id=\"builder-preview-rule\"></dd>";
if(facts&&access&&access.parentNode){facts.insertBefore(ruleRow,access.parentNode);}else if(facts){facts.appendChild(ruleRow);}}
text("builder-preview-rule",bp.rule);ruleRow.hidden=false;}else if(ruleRow){ruleRow.hidden=true;}
var groups=[];form.querySelectorAll("input[name=\"groups[]\"]:checked").forEach(function(input){var label=input.closest("label");var node=label&&label.querySelector(".builder__choice-label");groups.push(node?node.textContent:"");});
var levels=[];form.querySelectorAll("input[name=\"perm_levels[]\"]:checked").forEach(function(input){var label=input.closest("label");var node=label&&label.querySelector(".builder__choice-label");levels.push(node?node.textContent:"");});
text("builder-preview-access",(groups.filter(Boolean).join(", ")||"Administrators")+" · "+(levels.filter(Boolean).join(", ")||"Use, announcements, and settings"));
}
if(nameEl)nameEl.addEventListener("input",function(){if(!slugDirty&&slugEl)slugEl.value=slugify(nameEl.value);render();});
if(slugEl)slugEl.addEventListener("input",function(){slugDirty=slugEl.value.trim()!=="";if(!slugDirty&&nameEl)slugEl.value=slugify(nameEl.value);render();});
if(hintEl)hintEl.addEventListener("input",render);
if(typeEl)typeEl.addEventListener("input",function(){typeDirty=typeEl.value.trim()!=="";render();});
form.addEventListener("change",function(e){if(e.target&&e.target.name==="blueprint"){var bp=data.blueprints&&data.blueprints[e.target.value];
if(bp){form.querySelectorAll("input[name=\"screens[]\"]").forEach(function(input){input.checked=bp.screens.indexOf(input.value)!==-1;});
if(bp.icon){var iconInput=form.querySelector("input[name=icon][value=\""+bp.icon+"\"]");if(iconInput)iconInput.checked=true;}}}render();});
render();
var preview=document.querySelector(".builder__preview");
function placePreview(){
if(!preview)return;
var wide=window.matchMedia("(min-width:961px)").matches;
if(wide)preview.open=true;
else if(!preview.dataset.touched)preview.open=false;
}
if(preview){
preview.addEventListener("toggle",function(){preview.dataset.touched="1";});
placePreview();
window.addEventListener("resize",placePreview);
}
})();
