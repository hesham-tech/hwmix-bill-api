UPDATE transactions SET type = 'deposit' WHERE type = 'إيداع';
UPDATE transactions SET type = 'withdraw' WHERE type = 'سحب';
UPDATE transactions SET type = 'transfer_out' WHERE type IN ('تحويل_صادر', 'تحويل صادر');
UPDATE transactions SET type = 'transfer_in' WHERE type IN ('تحويل_وارد', 'تحويل وارد');
