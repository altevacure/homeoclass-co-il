"""Собирает формы CF7 из src/<набор>.json на основе шапки и подвала general-primary.form.html.
python gen.py acute.json  ->  ../<slug>.form.html, ../<slug>.mail.txt, ../forms.json (дополняется)
"""
import json, os, re, sys

here = os.path.dirname(os.path.abspath(__file__))
out = os.path.dirname(here)
base = open(os.path.join(out, 'general-primary.form.html'), encoding='utf-8').read()

cut = base.index('<div class="hq-q">')
head = base[:cut]
tail = base[base.index('<div class="hq-accept">'):]

BG = ('מה אתם לוקחים בקביעות: רמדיס הומאופתיים, תרופות, ויטמינים, תוספים? יש מחלות כרוניות, אלרגיה, ניתוחים לאחרונה? '
      'מעשנים? יש אבק, עשן, חומרי ניקוי או עובש בבית או בעבודה? נשים: הריון או הנקה.')
BG_CHILD = ('מה הילד לוקח בקביעות: רמדיס הומאופתיים, תרופות, ויטמינים, תוספים? יש מחלות כרוניות, אלרגיה, ניתוחים? '
            'יש בבית אבק, עשן, חומרי ניקוי או עובש? מעשנים בבית? ואם את בהיריון או מניקה, כתבו גם את זה.')
BG_LABEL = 'מה לוקחים בקביעות, מחלות כרוניות, אלרגיה, עישון, הריון'


def esc(s):
    return s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')


def build(f):
    slug = f['slug']
    h = head.replace('%2Fgeneral-primary%2F', '%2F' + slug + '%2F')
    h = re.sub(r'(<div class="hq-warn">).*?(</div>)',
               lambda m: m.group(1) + esc(f['warn']) + ' ההומאופת אינו רופא ואינו מחליף טיפול רפואי.' + m.group(2), h, count=1, flags=re.S)
    mail_who = 'עבור: [for-whom]\nגיל: [age]'
    if f.get('child'):
        h = h.replace('שם פרטי<span', 'שם פרטי של ההורה<span').replace('<span class="hq-l">שם משפחה</span>', '<span class="hq-l">שם משפחה של ההורה</span>').replace('>עליכם<', '>פרטי ההורה והילד<')
        h = re.sub(r'<div class="hq-f"><label><span class="hq-l">עבור מי השאלון.*?\[text age\]</label></div>',
                   lambda m: '<div class="hq-f"><label><span class="hq-l">שם הילד</span>[text* child-name]</label></div>\n\n'
                             '<div class="hq-f"><label><span class="hq-l">%s</span>[text child-age]</label></div>' % f['child'], h, flags=re.S)
        mail_who = 'עבור: [child-name]\nגיל: [child-age]'
    qs = list(f['qs']) + [[BG_LABEL, BG_CHILD if f.get('child') else BG]]
    body = ''
    mail = '<strong>%s</strong>\nמאת: [your-name] [your-surname] [your-email]\n%s\n\n' % (f['title'], mail_who)
    i = 0
    for q in qs:
        if len(q) == 1:
            body += '<div class="hq-card-t">%s</div>\n\n' % esc(q[0])
            mail += '<strong>%s</strong>\n\n' % q[0]
            continue
        label, text = q
        i += 1
        name = 'q%d' % i
        body += ('<div class="hq-q"><label><span class="hq-head"><span class="hq-n">%d</span><span class="hq-t">%s</span></span>'
                 '[textarea %s 130x4]</label></div>\n\n' % (i, esc(text), name))
        mail += '%d. %s\n<strong>[%s]</strong>\n\n' % (i, label, name)
    open(os.path.join(out, slug + '.form.html'), 'w', encoding='utf-8', newline='\n').write(h + body + tail)
    open(os.path.join(out, slug + '.mail.txt'), 'w', encoding='utf-8', newline='\n').write(mail.rstrip() + '\n')


forms = json.load(open(os.path.join(here, sys.argv[1]), encoding='utf-8'))
for f in forms:
    build(f)

mf = os.path.join(out, 'forms.json')
cur = json.load(open(mf, encoding='utf-8')) if os.path.exists(mf) else []
have = {c['slug'] for c in cur}
for f in forms:
    if f['slug'] not in have:
        cur.append({'slug': f['slug'], 'title': f['title']})
json.dump(cur, open(mf, 'w', encoding='utf-8', newline='\n'), ensure_ascii=False, indent=1)
print(len(forms), 'forms')
