<script setup>
import AppToasts from '@/components/ui/AppToasts.vue';
import NavigationProgress from '@/components/ui/NavigationProgress.vue';
</script>

<template>
    <NavigationProgress />
    <router-view />
    <AppToasts />
</template>
