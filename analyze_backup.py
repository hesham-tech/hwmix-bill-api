import json

# Read JSON file  
with open('u715355537_bill_api-5-12-2025.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Extract tables
tables = [item for item in data if item.get('type') == 'table']

empty_tables = []
non_empty_tables = []

for table in tables:
    table_name = table.get('name', 'Unknown')
    table_data = table.get('data', [])
    record_count = len(table_data)
    
    if record_count == 0:
        empty_tables.append(table_name)
    else:
        non_empty_tables.append((table_name, record_count))

# Write results to file
with open('backup_analysis_result.txt', 'w', encoding='utf-8') as f:
    f.write("=" * 80 + "\n")
    f.write("Database Backup Analysis - Table Status\n")
    f.write("=" * 80 + "\n\n")
    
    f.write(f"Total tables: {len(tables)}\n")
    f.write(f"Empty tables (no inserts): {len(empty_tables)}\n")
    f.write(f"Tables with data: {len(non_empty_tables)}\n\n")
    
    if len(empty_tables) == 0:
        f.write("=" * 80 + "\n")
        f.write("RESULT: NO EMPTY TABLES FOUND! ALL TABLES HAVE DATA.\n")
        f.write("=" * 80 + "\n\n")
    else:
        f.write("=" * 80 + "\n")
        f.write("EMPTY TABLES (No inserts):\n")
        f.write("=" * 80 + "\n")
        for idx, table_name in enumerate(empty_tables, 1):
            f.write(f"{idx}. {table_name}\n")
        f.write("\n")
    
    f.write("=" * 80 + "\n")
    f.write("TABLES WITH DATA:\n")
    f.write("=" * 80 + "\n")
    for table_name, count in sorted(non_empty_tables, key=lambda x: x[1], reverse=True):
        f.write(f"  - {table_name}: {count} records\n")

print("Analysis complete! Results saved to backup_analysis_result.txt")
