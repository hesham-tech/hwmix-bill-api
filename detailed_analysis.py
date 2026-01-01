import json
import os
import re

# Read JSON backup file  
with open('u715355537_bill_api-5-12-2025.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Extract table names from backup
backup_tables = set()
for item in data:
    if item.get('type') == 'table':
        backup_tables.add(item.get('name'))

# Read migration files to get expected tables
migration_dir = 'database/migrations'
expected_tables = set()

# Pattern to match table creation in migrations
create_pattern = re.compile(r"Schema::create\s*\(\s*['\"]([^'\"]+)['\"]")
# Pattern to match table name in migration filename
filename_pattern = re.compile(r'create_([a-z_]+)_table\.php$')

for filename in os.listdir(migration_dir):
    if filename.endswith('.php'):
        filepath = os.path.join(migration_dir, filename)
        
        # Try to extract from filename first
        match = filename_pattern.search(filename)
        if match:
           expected_tables.add(match.group(1))
        
        # Also read file content to be sure
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
                matches = create_pattern.findall(content)
                for table_name in matches:
                    expected_tables.add(table_name)
        except:
            pass

# Find tables that exist in migrations but not in backup (empty tables)
tables_not_in_backup = expected_tables - backup_tables

# Find tables in backup but not in migrations
tables_not_in_migrations = backup_tables - expected_tables

# Write detailed results
with open('detailed_table_analysis.txt', 'w', encoding='utf-8') as f:
    f.write("=" * 80 + "\n")
    f.write("DETAILED DATABASE ANALYSIS\n")
    f.write("Comparing Migrations vs Backup\n")
    f.write("=" * 80 + "\n\n")
    
    f.write(f"Tables in migrations: {len(expected_tables)}\n")
    f.write(f"Tables in backup: {len(backup_tables)}\n")
    f.write(f"Tables in migrations but NOT in backup: {len(tables_not_in_backup)}\n\n")
    
    if tables_not_in_backup:
        f.write("=" * 80 + "\n")
        f.write("TABLES WITH NO INSERTS (Exist in migrations but not in backup):\n")
        f.write("=" * 80 + "\n")
        for idx, table_name in enumerate(sorted(tables_not_in_backup), 1):
            f.write(f"{idx}. {table_name}\n")
        f.write("\n")
    else:
        f.write("=" * 80 + "\n")
        f.write("RESULT: ALL MIGRATION TABLES HAVE DATA IN BACKUP!\n")
        f.write("=" * 80 + "\n\n")
    
    f.write("=" * 80 + "\n")
    f.write("TABLES IN BACKUP:\n")
    f.write("=" * 80 + "\n")
    for table_name in sorted(backup_tables):
        f.write(f"  ✓ {table_name}\n")
    f.write("\n")
    
    if tables_not_in_migrations:
        f.write("=" * 80 + "\n")
        f.write("EXTRA TABLES (In backup but not found in migrations):\n")
        f.write("=" * 80 + "\n")
        for table_name in sorted(tables_not_in_migrations):
            f.write(f"  ! {table_name}\n")

print("Detailed analysis complete! Results saved to detailed_table_analysis.txt")
