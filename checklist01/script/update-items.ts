import { db } from '../server/db';
import { items } from '../shared/schema';

async function main() {
  // Insert 水・お茶 between コード(order=120) and マスク(order=130)
  const [newItem] = await db.insert(items).values({ name: '水・お茶', order: 125 }).returning();
  console.log(`Inserted: 水・お茶 (id=${newItem.id}, order=125)`);

  const all = await db.select().from(items).orderBy(items.order, items.id);
  console.log('\nFinal list:');
  all.forEach(r => console.log(`  order=${r.order} id=${r.id}: ${r.name}`));
}

main().then(() => process.exit(0)).catch(e => { console.error(e); process.exit(1); });
