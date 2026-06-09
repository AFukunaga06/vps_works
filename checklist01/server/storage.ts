import { items, entries, type Item, type InsertItem, type Entry, type InsertEntry } from "@shared/schema";
import { db } from "./db";
import { eq, and, gte, lte } from "drizzle-orm";

export interface IStorage {
  getItems(): Promise<Item[]>;
  createItem(item: InsertItem): Promise<Item>;
  updateItem(id: number, item: Partial<InsertItem>): Promise<Item>;
  deleteItem(id: number): Promise<void>;
  
  getEntries(startDate?: string, endDate?: string): Promise<Entry[]>;
  updateEntry(entry: InsertEntry): Promise<Entry>;
  clearEntries(startDate: string, endDate: string): Promise<void>;
}

export class DatabaseStorage implements IStorage {
  async getItems(): Promise<Item[]> {
    return await db.select().from(items).orderBy(items.order, items.id);
  }

  async createItem(insertItem: InsertItem): Promise<Item> {
    const [item] = await db.insert(items).values(insertItem).returning();
    return item;
  }

  async updateItem(id: number, updateItem: Partial<InsertItem>): Promise<Item> {
    const [item] = await db
      .update(items)
      .set(updateItem)
      .where(eq(items.id, id))
      .returning();
    return item;
  }

  async deleteItem(id: number): Promise<void> {
    await db.delete(items).where(eq(items.id, id));
    await db.delete(entries).where(eq(entries.itemId, id));
  }

  async getEntries(startDate?: string, endDate?: string): Promise<Entry[]> {
    let query = db.select().from(entries);
    if (startDate && endDate) {
        // @ts-ignore
        query = query.where(and(gte(entries.date, startDate), lte(entries.date, endDate)));
    }
    return await query;
  }

  async updateEntry(insertEntry: InsertEntry): Promise<Entry> {
    // Check if entry exists for item and date
    const existing = await db.select().from(entries).where(
      and(
        eq(entries.itemId, insertEntry.itemId),
        eq(entries.date, insertEntry.date)
      )
    ).limit(1);

    if (existing.length > 0) {
      const [updated] = await db
        .update(entries)
        .set({ status: insertEntry.status })
        .where(eq(entries.id, existing[0].id))
        .returning();
      return updated;
    } else {
      const [created] = await db.insert(entries).values(insertEntry).returning();
      return created;
    }
  }

  async clearEntries(startDate: string, endDate: string): Promise<void> {
    await db.delete(entries).where(
      and(gte(entries.date, startDate), lte(entries.date, endDate))
    );
  }
}

export const storage = new DatabaseStorage();
