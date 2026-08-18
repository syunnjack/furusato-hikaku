"""総務省「ふるさと納税に関する現況調査」の公表Excelから自治体データを組み立てる。

出典（令和8年8月公表）:
  - 各自治体の令和7年度受入額等
    https://www.soumu.go.jp/main_content/001084989.xlsx
  - 各自治体のふるさと納税受入額及び受入件数（平成20年度〜令和7年度）
    https://www.soumu.go.jp/main_content/001084990.xlsx
  - 各自治体の令和8年度課税における住民税控除額等
    https://www.soumu.go.jp/main_content/001085012.xlsx
  調査ページ: https://www.soumu.go.jp/main_sosiki/jichi_zeisei/czaisei/czaisei_seido/furusato/archive/

使い方: python scripts/build-municipality-data.py
  ダウンロードした2ファイルを読み、database/data/municipalities.json を書き出す。
  数値はすべて公表値そのままで、当サイト側での推計・補完はしない。
"""
import json
import re
import urllib.request
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

SOURCE_URL = 'https://www.soumu.go.jp/main_sosiki/jichi_zeisei/czaisei/czaisei_seido/furusato/archive/'
SURVEY_XLSX = 'https://www.soumu.go.jp/main_content/001084989.xlsx'
SERIES_XLSX = 'https://www.soumu.go.jp/main_content/001084990.xlsx'
DEDUCTION_XLSX = 'https://www.soumu.go.jp/main_content/001085012.xlsx'
FISCAL_YEAR = '令和7年度'
TAX_YEAR = '令和8年度課税'
OUTPUT = Path(__file__).resolve().parent.parent / 'database' / 'data' / 'municipalities.json'
CACHE = Path(__file__).resolve().parent / '.cache'

NS = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'


def download(url: str) -> Path:
    CACHE.mkdir(exist_ok=True)
    path = CACHE / url.rsplit('/', 1)[-1]
    if not path.exists():
        urllib.request.urlretrieve(url, path)
    return path


def sheet_rows(path: Path):
    """xlsxの1枚目のシートを、列名→値の辞書の一覧として読む。"""
    z = zipfile.ZipFile(path)
    shared = []
    if 'xl/sharedStrings.xml' in z.namelist():
        for si in ET.fromstring(z.read('xl/sharedStrings.xml')).findall(NS + 'si'):
            parts = []
            for child in si:
                # ふりがな（rPh）は本文ではないので拾わない
                if child.tag == NS + 't':
                    parts.append(child.text or '')
                elif child.tag == NS + 'r':
                    parts.extend(t.text or '' for t in child.findall(NS + 't'))
            shared.append(''.join(parts))

    rows = []
    for row in ET.fromstring(z.read('xl/worksheets/sheet1.xml')).iter(NS + 'row'):
        cells = {}
        for c in row.findall(NS + 'c'):
            col = re.match(r'[A-Z]+', c.get('r')).group(0)
            kind, v, inline = c.get('t'), c.find(NS + 'v'), c.find(NS + 'is')
            if kind == 'inlineStr' and inline is not None:
                cells[col] = ''.join(t.text or '' for t in inline.iter(NS + 't'))
            elif v is None:
                cells[col] = ''
            elif kind == 's':
                cells[col] = shared[int(v.text)]
            else:
                cells[col] = v.text
        rows.append(cells)
    return rows


FIELDS = {
    '①': 'まちづくり・市民活動', '②': 'スポーツ・文化振興', '③': '健康・医療・福祉',
    '④': '環境・衛生', '⑤': '教育・人づくり', '⑥': '子ども・子育て',
    '⑦': '地域・産業振興', '⑧': '観光・交流・定住促進', '⑨': '安心・安全・防災',
    '⑩': '災害支援・復興', '⑪': '分野を指定しない', '⑫': 'その他',
}
ONESTOP = {'①': '対応済み', '②': '令和8年度に対応予定', '③': '令和9年度以降に対応予定', '④': '対応予定なし'}

FIELD_COLS = [
    ('BJ', 'まちづくり・市民活動'), ('BM', 'スポーツ・文化振興'), ('BP', '健康・医療・福祉'),
    ('BS', '環境・衛生'), ('BV', '教育・人づくり'), ('BY', '子ども・子育て'),
    ('CB', '地域・産業振興'), ('CE', '観光・交流・定住促進'), ('CH', '安心・安全・防災'),
    ('CK', '災害支援・復興'), ('CN', '分野を指定しない'), ('CQ', 'その他'),
]

def colnum(name):
    n = 0
    for ch in name:
        n = n * 26 + (ord(ch) - 64)
    return n

def colshift(name, offset):
    n = colnum(name) + offset
    s = ''
    while n:
        n, rem = divmod(n - 1, 26)
        s = chr(65 + rem) + s
    return s

def num(v):
    if v in (None, '', '-', '－'):
        return None
    try:
        return float(v)
    except ValueError:
        return None

def yen(v):
    n = num(v)
    return None if n is None else int(round(n))

def text(v):
    if v in (None, '', '0', '-', '－'):
        return None
    return re.sub(r'[ \u3000]+$', '', str(v).replace('\r\n', '\n')).strip() or None

def project(row, cols):
    name = text(row.get(cols[0]))
    if not name:
        return None
    field = str(row.get(cols[3]) or '').strip()
    return {
        'name': name,
        'summary': text(row.get(cols[1])),
        'reward': text(row.get(cols[2])),
        'field': FIELDS.get(field),
        'target': yen(row.get(cols[4])),
        'actual': yen(row.get(cols[5])),
    }

rows = sheet_rows(download(SURVEY_XLSX))[12:]  # 12行目までは見出しと注記
records = {}
for r in rows:
    code = str(r.get('A') or '').strip()
    pref = text(r.get('B'))
    if not code or not pref:
        continue
    city = text(r.get('C'))
    projects = [p for p in (
        project(r, ['AR', 'AS', 'AT', 'AU', 'AV', 'AW']),
        project(r, ['AX', 'AY', 'AZ', 'BA', 'BB', 'BC']),
        project(r, ['BD', 'BE', 'BF', 'BG', 'BH', 'BI']),
    ) if p]
    ratio = num(r.get('AF'))
    breakdown = []
    for mark, label in FIELD_COLS:
        if str(r.get(mark) or '').strip() != '○':
            continue
        # ⑫その他だけ、○の次に分野名の列が挟まってから件数・金額が並ぶ
        shift = 2 if mark == 'CQ' else 1
        name = text(r.get(colshift(mark, 1))) if mark == 'CQ' else label
        breakdown.append({
            'field': name or label,
            'count': yen(r.get(colshift(mark, shift))),
            'amount': yen(r.get(colshift(mark, shift + 1))),
        })
    breakdown = [b for b in breakdown if (b['amount'] or 0) > 0]
    breakdown.sort(key=lambda b: -(b['amount'] or 0))
    records[(pref, city)] = {
        'code': code,
        'prefecture': pref,
        'city': city,
        'count': yen(r.get('D')),
        'amount': yen(r.get('E')),
        'outside_count': yen(r.get('F')),
        'outside_amount': yen(r.get('G')),
        'cost_total': yen(r.get('R')),
        'cost_ratio': None if ratio is None else round(ratio * 100, 1),
        'reward_provided': None if not str(r.get('DM') or '').strip() else str(r.get('DM')).strip() == '①',
        'use_selectable': str(r.get('AJ') or '').strip() == '①',
        'use_by_project': str(r.get('AL') or '').strip() == '○',
        'use_by_field': str(r.get('AM') or '').strip() == '○',
        'cf_projects': yen(r.get('AP')),
        'cf_amount': yen(r.get('AQ')),
        'projects': projects,
        'field_breakdown': breakdown,
        'publish_amount': str(r.get('DG') or '').strip() == '○',
        'publish_usage': str(r.get('DH') or '').strip() == '○',
        'publish_progress': str(r.get('DI') or '').strip() == '○',
        'donor_relation': text(r.get('DL')),
        'onestop_online': ONESTOP.get(str(r.get('AH') or '').strip()),
        'series': [],
        'deduction': None,
    }

# 受入額の推移（平成20年度〜令和7年度）。単位は千円なので円に直す。
YEARS = ['平成20年度', '平成21年度', '平成22年度', '平成23年度', '平成24年度', '平成25年度',
         '平成26年度', '平成27年度', '平成28年度', '平成29年度', '平成30年度', '令和元年度',
         '令和2年度', '令和3年度', '令和4年度', '令和5年度', '令和6年度', '令和7年度']
AMOUNT_COLS = [colshift('C', n * 2) for n in range(len(YEARS))]  # C, E, G, ... が各年度の金額

unmatched = []
for r in sheet_rows(download(SERIES_XLSX))[4:]:
    pref = text(r.get('A'))
    if not pref:
        continue
    city = text(r.get('B'))
    rec = records.get((pref, city))
    if rec is None:
        unmatched.append((pref, city))
        continue
    series = []
    for year, col in zip(YEARS, AMOUNT_COLS):
        amount = num(r.get(col))
        count = num(r.get(colshift(col, 1)))  # 金額の隣の列が件数
        series.append({
            'year': year,
            'amount': None if amount is None else int(round(amount * 1000)),  # 元データは千円
            'count': None if count is None else int(round(count)),
        })
    rec['series'] = series

# 住民税の控除状況（＝その自治体に住む人がふるさと納税をした側の数字）。
# 団体コードは先頭の0が落ちた5桁で入っているため、6桁にそろえて突き合わせる。
by_code = {r['code']: r for r in records.values()}
deduction_unmatched = 0
for r in sheet_rows(download(DEDUCTION_XLSX))[18:]:  # 18行目までは注記と見出し
    code = str(r.get('A') or '').strip()
    if not code:
        continue
    rec = by_code.get(code.zfill(6))
    if rec is None:
        deduction_unmatched += 1
        continue
    municipal_tax = yen(r.get('F'))    # 市町村民税からの控除額
    prefectural_tax = yen(r.get('M'))  # 道府県民税からの控除額
    rec['deduction'] = {
        'taxYear': TAX_YEAR,
        'people': yen(r.get('D')),
        'donation': yen(r.get('E')),
        'amount': None if municipal_tax is None and prefectural_tax is None
                  else (municipal_tax or 0) + (prefectural_tax or 0),
        'onestopPeople': yen(r.get('G')),
        'onestopDonation': yen(r.get('H')),
    }

out = sorted(records.values(), key=lambda x: x['code'])
payload = {
    'fiscalYear': FISCAL_YEAR,
    'taxYear': TAX_YEAR,
    'sourceLabel': '総務省「ふるさと納税に関する現況調査結果（令和8年度実施）」',
    'sourceUrl': SOURCE_URL,
    'municipalities': out,
}
OUTPUT.write_text(json.dumps(payload, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
print(f'{len(out)}団体を書き出しました（推移データあり: {sum(1 for r in out if r["series"])}、'
      f'控除データあり: {sum(1 for r in out if r["deduction"])}、'
      f'突合できなかった行: 推移{len(unmatched)} / 控除{deduction_unmatched}）')
