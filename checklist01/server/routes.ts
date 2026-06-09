import type { Express } from "express";
import type { Server } from "http";
import { storage } from "./storage";
import { api } from "@shared/routes";
import { z } from "zod";

export async function registerRoutes(
  httpServer: Server,
  app: Express
): Promise<Server> {
  
  app.get(api.items.list.path, async (req, res) => {
    const items = await storage.getItems();
    res.json(items);
  });

  app.post(api.items.create.path, async (req, res) => {
    try {
      const input = api.items.create.input.parse(req.body);
      const item = await storage.createItem(input);
      res.status(201).json(item);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({
          message: err.errors[0].message,
          field: err.errors[0].path.join('.'),
        });
      }
      throw err;
    }
  });

  app.put(api.items.update.path, async (req, res) => {
    try {
      const id = parseInt(req.params.id);
      const input = api.items.update.input.parse(req.body);
      const item = await storage.updateItem(id, input);
      if (!item) {
        return res.status(404).json({ message: "Item not found" });
      }
      res.json(item);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({
          message: err.errors[0].message,
        });
      }
      throw err;
    }
  });

  app.delete(api.items.delete.path, async (req, res) => {
    const id = parseInt(req.params.id);
    await storage.deleteItem(id);
    res.status(204).end();
  });

  app.get(api.entries.list.path, async (req, res) => {
    const startDate = req.query.startDate as string | undefined;
    const endDate = req.query.endDate as string | undefined;
    const entries = await storage.getEntries(startDate, endDate);
    res.json(entries);
  });

  app.post(api.entries.update.path, async (req, res) => {
    try {
      const input = api.entries.update.input.parse(req.body);
      const entry = await storage.updateEntry(input);
      res.json(entry);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({
          message: err.errors[0].message,
        });
      }
      throw err;
    }
  });

  app.post(api.entries.bulkClear.path, async (req, res) => {
      const input = api.entries.bulkClear.input.parse(req.body);
      await storage.clearEntries(input.startDate, input.endDate);
      res.json({ success: true });
  });

  // Seed data
  const items = await storage.getItems();
  if (items.length === 0) {
    const defaultItems = [
      "財布(封筒)",
      "定期券(パス券)",
      "障害者手帳",
      "鍵",
      "ノート1、2、3ペン",
      "携帯電話",
      "スケジュール帳",
      "MEMO",
      "入れ歯",
      "腕時計",
      "お薬手帳",
      "充電器",
      "コード",
      "マスク",
      "ウォークマン",
      "ヘッドホン/イヤホン",
      "傘・傘入れ",
      "お薬"
    ];
    for (const name of defaultItems) {
      await storage.createItem({ name, order: 0 });
    }
  }

  return httpServer;
}
