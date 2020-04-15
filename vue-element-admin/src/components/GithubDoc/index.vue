<template>
  <div>
    <svg-icon icon-class="question" @click="goto"/>
  </div>
</template>

<script>
export default {
  name: 'GithubDoc',
  data() {
    return {
      url: 'https://github.com/emacle/vue-php-admin'
    }
  },
  methods: {
    goto() {
      window.open(this.url)
    }
  }
}
</script>
