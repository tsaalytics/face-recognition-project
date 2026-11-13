import test from 'node:test';
import assert from 'node:assert/strict';
import { setupSubmission, setupNavigation, setupDecision, setupEvidencePreview } from '../../resources/js/ui.js';
function element() {
 const listeners=new Map(),attrs=new Map(),classes=new Set();
 return {
  listeners,attrs,classes,disabled:false,textContent:'Simpan',required:false,value:'',focused:false,
  addEventListener(name,fn) { listeners.set(name,fn); },
  setAttribute(name,value) { attrs.set(name,value); },getAttribute(name) { return attrs.get(name); },removeAttribute(name) { attrs.delete(name); },
  classList:{add(v){classes.add(v);},remove(v){classes.delete(v);},toggle(v,on){if(on)classes.add(v);else classes.delete(v);}},
  focus(){this.focused=true;}
 };
}
test('submission locks once and restores label after back navigation',()=>{
 const button=element(),form=element(),browser=element();form.querySelector=()=>button;form.reportValidity=()=>true;
 setupSubmission(form,browser);
 let prevented=0;const event={preventDefault(){prevented++;}};
 form.listeners.get('submit')(event);
 assert.equal(button.disabled,true);assert.equal(button.textContent,'Memproses...');
 assert.equal(form.attrs.get('aria-busy'),'true');
 form.listeners.get('submit')(event);assert.equal(prevented,1);
 browser.listeners.get('pageshow')();
 assert.equal(button.disabled,false);assert.equal(button.textContent,'Simpan');assert.equal(form.attrs.has('aria-busy'),false);
 form.listeners.get('submit')(event);assert.equal(button.disabled,true);
});
test('invalid form does not enter loading state and missing button is harmless',()=>{
 const button=element(),form=element(),browser=element();form.querySelector=()=>button;form.reportValidity=()=>false;
 setupSubmission(form,browser);let prevented=false;
 form.listeners.get('submit')({preventDefault(){prevented=true;}});
 assert.equal(prevented,true);assert.equal(button.disabled,false);
 assert.doesNotThrow(()=>setupSubmission({querySelector:()=>null},browser));
});
test('submission cancelled by another handler keeps the form usable',()=>{
 const button=element(),form=element(),browser=element();form.querySelector=()=>button;form.reportValidity=()=>true;
 setupSubmission(form,browser);
 form.listeners.get('submit')({defaultPrevented:true});
 assert.equal(button.disabled,false);assert.equal(button.textContent,'Simpan');assert.equal(form.attrs.has('aria-busy'),false);
});
test('mobile navigation toggles aria and Escape returns keyboard focus',()=>{
 const button=element(),nav=element();button.setAttribute('aria-expanded','false');
 setupNavigation(button,nav);button.listeners.get('click')();
 assert.equal(button.getAttribute('aria-expanded'),'true');assert.equal(nav.classes.has('hidden'),false);
 nav.listeners.get('keydown')({key:'Escape'});
 assert.equal(button.getAttribute('aria-expanded'),'false');assert.equal(nav.classes.has('hidden'),true);assert.equal(button.focused,true);
});
test('reject requires notes in browser while approve keeps notes optional',()=>{
 const decision=element(),notes=element();
 setupDecision({querySelector:(selector)=>selector.includes('decision')?decision:notes});
 assert.equal(notes.required,false);
 decision.value='rejected';decision.listeners.get('change')();assert.equal(notes.required,true);assert.equal(notes.getAttribute('aria-required'),'true');
 decision.value='approved';decision.listeners.get('change')();assert.equal(notes.required,false);
});
test('broken or previously failed image shows useful fallback without hiding download',()=>{
 const image=element(),message=element();message.classes.add('hidden');
 setupEvidencePreview(image,message);image.listeners.get('error')();
 assert.equal(image.classes.has('hidden'),true);assert.equal(message.classes.has('hidden'),false);
 const cached=element(),cachedMessage=element();cached.complete=true;cached.naturalWidth=0;cachedMessage.classes.add('hidden');
 setupEvidencePreview(cached,cachedMessage);assert.equal(cachedMessage.classes.has('hidden'),false);
});
