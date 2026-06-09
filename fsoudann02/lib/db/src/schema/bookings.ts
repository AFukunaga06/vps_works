import { mysqlTable, serial, text, date, timestamp, int } from "drizzle-orm/mysql-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const bookingsTable = mysqlTable("bookings", {
  id: serial("id").primaryKey(),
  date: date("date").notNull(),
  time: text("time").notNull(),
  name: text("name").notNull(),
  email: text("email").notNull(),
  phone: text("phone"),
  notes: text("notes"),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const insertBookingSchema = createInsertSchema(bookingsTable).omit({ id: true, createdAt: true });
export type InsertBooking = z.infer<typeof insertBookingSchema>;
export type Booking = typeof bookingsTable.$inferSelect;

export const availableSlotsTable = mysqlTable("available_slots", {
  id: serial("id").primaryKey(),
  date: date("date").notNull(),
  maxSlots: int("max_slots").notNull().default(8),
});

export type AvailableSlot = typeof availableSlotsTable.$inferSelect;
