import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Register MyFramework Mobile UX Controllers
import ToastController from './controllers/myframework/toast_controller.js';
import SwipeableController from './controllers/myframework/swipeable_controller.js';
import LongpressController from './controllers/myframework/longpress_controller.js';
import PullrefreshController from './controllers/myframework/pullrefresh_controller.js';

app.register('myframework--toast', ToastController);
app.register('myframework--swipeable', SwipeableController);
app.register('myframework--longpress', LongpressController);
app.register('myframework--pullrefresh', PullrefreshController);
