<script setup>
// Pictogrammes au trait (24 × 24), toujours décoratifs : le texte voisin porte le sens.
defineProps({ name: { type: String, required: true } })

const paths = {
  lock: ['M5 11h14v10H5z', 'M8 11V8a4 4 0 0 1 8 0v3'],
  calendar: ['M3 5h18v16H3z', 'M3 10h18M8 3v4M16 3v4'],
  users: [
    'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
    'M2 21c0-4 3-6 7-6s7 2 7 6',
    'M16 3.5a4 4 0 0 1 0 7.5M18 15c2.5.5 4 2.5 4 6',
  ],
  star: ['m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z'],
  trash: ['M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3'],
  mail: ['M3 5h18v14H3z', 'm3 6 9 7 9-7'],
  file: ['M6 2h9l5 5v15H6z', 'M14 2v6h6M9 13h8M9 17h8'],
  pen: ['M4 20h4L19 9l-4-4L4 16z', 'm13 7 4 4'],
  note: ['M5 3h14v18H5z', 'M8 8h8M8 12h8M8 16h5'],
  pin: ['M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z', 'M12 6.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z'],
  tag: ['M3 12V4h8l10 10-8 8z', 'M7.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z'],
  flag: ['M5 21V4', 'M5 4h12l-2 4 2 4H5'],
  user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21c0-4 4-6 8-6s8 2 8 6'],
  building: ['M4 21V3h11v18M15 9h5v12', 'M8 7h3M8 11h3M8 15h3M2 21h20'],
  phone: ['M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A18 18 0 0 1 3 5a2 2 0 0 1 2-2z'],
  search: ['M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z', 'm20 20-4-4'],
  info: ['M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z', 'M12 11v6M12 7.5v.5'],
  server: ['M3 4h18v7H3zM3 13h18v7H3z', 'M7 7.5h.01M7 16.5h.01'],
  arrow: ['M5 12h14M13 6l6 6-6 6'],
}
</script>
<template>
  <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
    <path v-for="d in paths[name]" :key="d" :d="d" />
  </svg>
</template>
<style scoped>
.icon {
  flex: none;
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>
