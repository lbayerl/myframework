import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// MyFramework Mobile UX Controllers
import './controllers/myframework/toast_controller.js';
import './controllers/myframework/swipeable_controller.js';
import './controllers/myframework/longpress_controller.js';
import './controllers/myframework/pullrefresh_controller.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
