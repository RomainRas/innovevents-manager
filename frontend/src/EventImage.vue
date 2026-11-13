<script setup>
import { nextTick, ref } from 'vue'
import { auth, request } from './auth'
const props=defineProps({id:String,revision:Number,alternative:String,hasImage:Boolean,disabled:Boolean})
const emit=defineEmits(['saved','locked'])
const alt=ref(props.alternative||''),file=ref(null),busy=ref(false),uncertain=ref(false),message=ref(''),error=ref(''),fields=ref({})
let pending=null
async function upload(){
 busy.value=true;emit('locked',true);message.value='';error.value='';fields.value={}
 try{
  if(!pending){pending=new FormData();pending.append('id',props.id);pending.append('revision',String(props.revision));pending.append('alternative',alt.value);if(file.value)pending.append('image',file.value)}
  const d=await request('/api/admin/event-image-upload',{method:'POST',headers:{'X-CSRF-Token':auth.csrf},body:pending})
  pending=null;uncertain.value=false;emit('saved',d);message.value='Image enregistrée.'
  await nextTick();document.getElementById('image-result')?.focus()
 }catch(e){
  fields.value=e.fields||{}
  if(e.status===422){error.value='Vérifiez l’image et sa description.';pending=null;uncertain.value=false}
  else if(e.status===413){error.value='L’image ne doit pas dépasser 5 Mo.';pending=null;uncertain.value=false}
  else if(e.status===409){error.value='L’événement a changé. Rechargez la fiche avant de remplacer l’image.';pending=null;uncertain.value=false}
  else if(e.status===401||e.status===403){error.value='Votre session ou vos droits ne permettent plus cette action.';pending=null;uncertain.value=false}
  else{error.value='Enregistrement non confirmé. Réessayez avec la même image.';uncertain.value=true}
  busy.value=false;await nextTick();(document.getElementById(Object.keys(fields.value)[0])||document.getElementById('image-error'))?.focus()
 }finally{busy.value=false;emit('locked',uncertain.value)}
}
</script>
<template><section aria-labelledby="image-title"><h2 id="image-title">Image de l’événement</h2>
 <img v-if="hasImage" :src="'/api/admin/event-image?id='+id+'&revision='+revision" :alt="alternative" class="event-picture">
 <p v-else>Aucune image enregistrée.</p>
 <p id="image-help">JPEG ou PNG, 5 Mo maximum, 4 096 pixels par côté et 8 millions de pixels au total. L’image reste privée dans ce brouillon.</p>
 <p v-if="error" id="image-error" role="alert" tabindex="-1" class="error">{{ error }}</p><p v-if="message" id="image-result" role="status" tabindex="-1">{{ message }}</p>
 <form @submit.prevent="upload" novalidate :aria-busy="busy">
 <label for="event-image">{{ hasImage?'Remplacer l’image':'Choisir une image' }}</label><input id="event-image" type="file" accept="image/jpeg,image/png" :disabled="disabled||busy||uncertain" :aria-invalid="!!fields['event-image']" :aria-describedby="fields['event-image']?'image-help image-file-error':'image-help'" @change="file=$event.target.files[0]||null"><p v-if="fields['event-image']" id="image-file-error" class="error">{{ fields['event-image'] }}</p>
 <label for="image-alternative">Description de l’image *</label><input id="image-alternative" v-model="alt" maxlength="255" required :disabled="disabled||busy||uncertain" :aria-invalid="!!fields['image-alternative']" :aria-describedby="fields['image-alternative']?'image-alt-error':undefined"><p v-if="fields['image-alternative']" id="image-alt-error" class="error">{{ fields['image-alternative'] }}</p>
 <p><button :disabled="disabled||busy">{{ busy?'Enregistrement…':uncertain?'Vérifier et réessayer l’image':'Enregistrer l’image' }}</button></p>
 </form></section></template>
<style scoped>.event-picture{display:block;max-width:100%;max-height:360px;object-fit:contain}section{border-top:1px solid #CBD5E1;margin-top:32px;padding-top:16px}input[type=file]{max-width:100%;box-sizing:border-box}[aria-invalid=true]{border:2px solid #B42318}</style>
