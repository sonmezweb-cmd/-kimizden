#!/usr/bin/env python3
"""TR/EN/RU dil dosyalarını üretir ve koddaki tüm anahtarların tanımlı olduğunu denetler."""
import os, re, sys, glob
sys.path.insert(0, os.path.dirname(__file__))
from strings_a import S as A
from strings_b import S as B
from strings_c import S as C

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
S = {}
for part in (A, B, C):
    dup = set(S) & set(part)
    if dup:
        sys.exit('çift anahtar: %s' % dup)
    S.update(part)

def php(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

for i, lang in enumerate(['tr', 'en', 'ru']):
    lines = ['<?php', '// Otomatik üretildi: tools/i18n/build.py — yönetim panelinden geçersiz kılınabilir.', 'return [']
    for k in sorted(S):
        lines.append('    %s => %s,' % (php(k), php(S[k][i])))
    lines.append('];')
    with open(os.path.join(ROOT, 'app', 'lang', lang + '.php'), 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')

# denetim: koddaki sabit anahtarlar
used = set()
for f in glob.glob(os.path.join(ROOT, 'app', '**', '*.php'), recursive=True):
    src = open(f, encoding='utf-8').read()
    used |= set(re.findall(r"\bt\('([a-z0-9_.]+)'", src))
    used |= set(re.findall(r"'((?:next|error|order\.err|pay\.err|publish\.err|approve\.err|revision\.err|upload\.err|v|form\.events|form\.names|form\.texts)\.[a-z_.]+)'", src))
dynamic = [k for k in used if k.endswith('.')]
missing = sorted(k for k in used if not k.endswith('.') and k not in S and k not in ('faq.q', 'faq.a'))
# değişken anahtar aileleri
fam = {
    'status.pay.': ['unpaid', 'awaiting', 'paid', 'test_paid', 'review'],
    'status.design.': ['info_pending', 'in_design', 'awaiting_approval', 'revision_requested', 'approved'],
    'status.pub.': ['unpublished', 'published', 'archived'],
    'vstatus.': ['sent', 'revision_requested', 'approved', 'superseded'],
    'pay.state.': ['paid', 'test_paid', 'review', 'verifying', 'failed', 'unpaid'],
    'form.step.': ['names', 'languages', 'events', 'texts', 'details', 'review'],
    'event.type.': ['engagement', 'henna', 'civil', 'wedding', 'cocktail', 'dinner', 'other'],
    'role.': ['customer', 'designer', 'admin', 'customers', 'designers', 'admins'],
    'mailst.': ['pending', 'sent', 'failed'],
    'legal.': ['privacy', 'terms', 'kvkk', 'cookies'],
    'admin.settings.': ['general', 'payments', 'smtp', 'legal'],
    'paytr.mode.': ['test', 'live'],
    'tab.': ['overview', 'form', 'photos', 'preview', 'share', 'guests', 'rsvps'],
}
for k in ['intro', 'letter', 'quote', 'quote_by', 'dress', 'transport', 'stay', 'notes']:
    missing += [x for x in ('form.texts.' + k, 'form.texts.' + k + '_help') if x not in S]
for tpl in ['paid', 'draft_ready', 'revision', 'approved', 'published', 'reset', 'admin_review', 'test', 'info_submitted']:
    missing += [x for x in ('mail.%s.subject' % tpl, 'mail.%s.body' % tpl) if x not in S]
for p, items in fam.items():
    missing += [p + i for i in items if p + i not in S]
for i in range(1, 11):
    missing += [x for x in ('faq.q%d' % i, 'faq.a%d' % i) if x not in S]
for s in ['choose', 'pay', 'info', 'draft', 'approve', 'share']:
    missing += [x for x in ('how.%s.title' % s, 'how.%s.text' % s) if x not in S]
print('anahtar:', len(S), '· eksik:', len(missing))
for m in sorted(set(missing)):
    print('  EKSİK', m)
sys.exit(1 if missing else 0)
