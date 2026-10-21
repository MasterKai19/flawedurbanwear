import {randomBytes} from 'node:crypto';
console.log('Copy these directly into Vercel Environment Variables; do not share them in chat.');
console.log('OWNER_PASSWORD='+randomBytes(32).toString('hex'));
console.log('CRON_SECRET='+randomBytes(32).toString('hex'));
