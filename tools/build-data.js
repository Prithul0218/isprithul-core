/* Builds data/defaults.json and data/starter.json from the homepage mockup.
   usage: node tools/build-data.js <mockup content.js> <photos.json from the media branch>
   defaults.json: every Site content field as it appears in the admin form (raw text).
   starter.json:  the projects and photo list the starter-content importer creates. */
const fs = require('fs'), path = require('path');
const [contentFile, photosFile] = process.argv.slice(2);
global.window = global;
require(path.resolve(contentFile));
const C = global.ISP.content;
const photos = JSON.parse(fs.readFileSync(photosFile, 'utf8'));
const lines = (rows, cols) => rows.map(r => cols.map(c => (r[c] == null ? '' : String(r[c]))).join(' | ').replace(/( \| )+$/, '')).join('\n');

const target = q => q.open || q.filter || (q.brief ? 'brief' : '');
const d = {
  'site.name': C.site.name,
  'site.email': C.site.email,
  'site.available': C.site.available ? '1' : '0',
  'site.statusOn': C.site.statusOn,
  'site.statusOff': C.site.statusOff,
  'site.headerButton': C.site.headerButton,
  'site.nav': lines(C.site.nav, ['label', 'href']),
  'site.socials': lines(C.site.socials, ['label', 'href']),
  'hero.questionStart': C.hero.questionStart,
  'hero.questions': C.hero.questions.map(q => [q.q, q.a, target(q), q.aBooked || ''].join(' | ').replace(/( \| )+$/, '')).join('\n'),
  'hero.title': C.hero.title,
  'hero.intro': C.hero.intro,
  'hero.button': C.hero.button,
  'hero.secondButton': C.hero.secondButton
};
Object.keys(C.probe).forEach(k => { d['probe.' + k] = C.probe[k]; });
['title', 'all'].forEach(k => { d['projectsSection.' + k] = C.projectsSection[k]; });
C.projectsSection.groups.forEach(g => { d['projectsSection.groups.' + g.id] = g.label; });
['surprise', 'similarButton', 'prev', 'next'].forEach(k => { d['projectsSection.' + k] = C.projectsSection[k]; });
d['services.title'] = C.services.title;
C.services.list.forEach(s => { ['name', 'text', 'link'].forEach(k => { d['services.' + s.id + '.' + k] = s[k]; }); });
d['services.processTitle'] = C.services.processTitle;
d['services.process'] = lines(C.services.process, ['step', 'title', 'text']);
d['clients.title'] = C.clients.title;
d['clients.link.label'] = C.clients.link.label;
d['clients.link.href'] = C.clients.link.href;
d['clients.stats'] = lines(C.clients.stats, ['n', 'label']);
['text', 'name', 'role', 'source'].forEach(k => { d['clients.featured.' + k] = C.clients.featured[k]; });
d['clients.quotes'] = lines(C.clients.quotes, ['text', 'who']);
d['experience.title'] = C.experience.title;
d['experience.list'] = lines(C.experience.list, ['when', 'title', 'where']);
d['about.title'] = C.about.title;
d['about.photo'] = '0';
d['about.text'] = C.about.text.join('\n\n');
d['about.facts'] = C.about.facts.join('\n');
Object.keys(C.contact).forEach(k => { d['contact.' + k] = C.contact[k]; });
d['footer.copyright'] = C.footer.copyright.replace(/© \d{4}/, '© {year}');
d['footer.legal'] = C.footer.legal;
d['footer.legalPage'] = '0';
d['footer.rev'] = C.footer.rev;
Object.keys(C.brief).forEach(k => {
  if (k === 'timelineOptions') d['brief.timelineOptions'] = C.brief.timelineOptions.join('\n');
  else d['brief.' + k] = C.brief[k];
});
/* the mockup's "nothing was sent" texts become the live ones */
d['brief.doneTitle'] = 'Brief sent ✓';
d['brief.doneText'] = 'Thanks! It’s in my inbox now, and I reply within one business day.';
d['brief.errorSend'] = 'Sending didn’t work. Please email me at';
d['brief.notify'] = '';
fs.writeFileSync('data/defaults.json', JSON.stringify(d, null, 1) + '\n');

const byProject = {};
photos.forEach(p => { (byProject[p.project] = byProject[p.project] || []).push({ file: p.file, alt: p.alt }); });
const starter = {
  media_base: 'https://raw.githubusercontent.com/Prithul0218/isprithul-core/media/photos/',
  projects: C.projects.map(p => ({
    id: p.id, group: p.group, title: p.title, tagline: p.tagline, type: p.type, year: p.year, badge: p.badge,
    services: p.services.join(','), summary: p.summary,
    facts: (p.facts || []).map(f => f.join(' | ')).join('\n'),
    quote_text: p.quote ? p.quote.text : '', quote_who: p.quote ? p.quote.who : '',
    photos: byProject[p.id] || []
  })),
  about_photo: (byProject.about || [])[0] || null
};
starter.projects.forEach(p => { if (!p.photos.length) throw new Error('no photos for ' + p.id); });
fs.writeFileSync('data/starter.json', JSON.stringify(starter, null, 1) + '\n');
console.log(Object.keys(d).length, 'fields;', starter.projects.length, 'projects;', photos.length, 'photos');
