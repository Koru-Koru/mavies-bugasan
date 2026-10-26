<template>
    <div class="customer-screen">
        <div class="confirm">
            <div class="big-check" aria-hidden="true">✅</div>
            <h2>Sign Up Success.</h2>
            <p class="note">Your account is ready. Sign in to start ordering!</p>

            <div class="confirm-actions">
                <router-link class="btn btn-dark" to="/login">SIGN IN</router-link>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'RegisterSuccess',
};
</script>