import { createApp} from "vue";
import Layout from "@/components/Layout.vue";

import { library } from '@fortawesome/fontawesome-svg-core';
import { faFileContract, faUserSecret } from '@fortawesome/free-solid-svg-icons';
import { faGitlab, faGithub, faDiscord } from '@fortawesome/free-brands-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

library.add(faFileContract, faGitlab, faGithub, faDiscord, faUserSecret);

const app = createApp({});

app.component('Layout', Layout)
    .component('font-awesome-icon', FontAwesomeIcon)
    .mount('#app')