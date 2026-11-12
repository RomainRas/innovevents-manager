<script setup>
import { onMounted, ref } from 'vue'
const message = ref('Vérification de la connexion…')
async function check() {
  message.value = 'Vérification de la connexion…'
  try {
    const response = await fetch('/api/health', { signal: AbortSignal.timeout(5000) })
    const data = await response.json()
    if (!response.ok || data.status !== 'ready') throw new Error('unavailable')
    message.value = 'La connexion est disponible.'
  } catch { message.value = 'La connexion est indisponible. Vous pouvez réessayer.' }
}
onMounted(check)
</script>
<template>
  <main>
    <p class="brand">Innov’Events</p>
    <h1>Votre espace se prépare</h1>
    <p>Les fonctionnalités de gestion des événements seront ajoutées progressivement.</p>
    <p role="status" aria-live="polite">{{ message }}</p>
    <button type="button" @click="check">Vérifier la connexion</button>
  </main>
</template>
<style scoped>
:root { font-family: system-ui, sans-serif; color: #17212b; background: #f5f6f8; }
body { margin: 0; } main { max-width: 640px; margin: 10vh auto; padding: 32px; background: white; border-radius: 16px; }
.brand { font-weight: 700; } h1 { font-size: clamp(1.8rem, 5vw, 2.8rem); }
p { line-height: 1.6; } button { font: inherit; padding: 12px 18px; border: 0; border-radius: 6px; color: white; background: #17212b; cursor: pointer; }
button:focus-visible { outline: 3px solid #3366cc; outline-offset: 3px; }
@media(max-width:700px) { main { margin: 24px 16px; padding: 24px; } }
</style>
