
const fs = require('fs');
const file = 'config/permissions_keys.php';
let content = fs.readFileSync(file, 'utf8');
content = content.replace(
  /'transfer' => \['key' => 'balance.transfer', 'label' => '.*?'\],/,
  "'transfer' => ['key' => 'balance.transfer', 'label' => 'تحويل رصيد (شخصي)'],\n        'adjust_balance' => ['key' => 'cashbox.adjust_balance', 'label' => 'مطابقة وتسوية الأرصدة'],"
);
fs.writeFileSync(file, content);

