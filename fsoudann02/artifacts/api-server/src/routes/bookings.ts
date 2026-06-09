import { Router, type IRouter } from "express";
import { db } from "@workspace/db";
import { bookingsTable } from "@workspace/db/schema";
import { eq, and, gte, lte } from "drizzle-orm";
import { z } from "zod";
import {
  GetBookingsQueryParams,
} from "@workspace/api-zod";

const AvailabilityQueryParams = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
});

const CreateBookingBodySchema = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  time: z.string(),
  name: z.string(),
  email: z.string().email(),
  phone: z.string().optional(),
  notes: z.string().optional(),
});

const router: IRouter = Router();

const TIME_SLOTS = [
  "10:00",
  "11:00",
  "13:00",
  "14:00",
  "15:00",
  "16:00",
  "17:00",
];
const MAX_LEGAL_SLOTS = 8;

router.get("/bookings", async (req, res) => {
  const queryResult = GetBookingsQueryParams.safeParse(req.query);
  if (!queryResult.success) {
    res.status(400).json({ error: "Invalid query parameters" });
    return;
  }

  const { year, month } = queryResult.data;
  const now = new Date();
  const targetYear = year ?? now.getFullYear();
  const targetMonth = month ?? now.getMonth() + 1;

  const startDate = `${targetYear}-${String(targetMonth).padStart(2, "0")}-01`;
  const lastDay = new Date(targetYear, targetMonth, 0).getDate();
  const endDate = `${targetYear}-${String(targetMonth).padStart(2, "0")}-${lastDay}`;

  const bookings = await db
    .select()
    .from(bookingsTable)
    .where(
      and(gte(bookingsTable.date, startDate), lte(bookingsTable.date, endDate))
    );

  const slotsByDate: Record<string, number> = {};
  for (const booking of bookings) {
    slotsByDate[booking.date] = (slotsByDate[booking.date] ?? 0) + 1;
  }

  const result = [];
  for (let day = 1; day <= lastDay; day++) {
    const dateStr = `${targetYear}-${String(targetMonth).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
    const dayOfWeek = new Date(dateStr).getDay();
    if (dayOfWeek === 0) continue;

    const booked = slotsByDate[dateStr] ?? 0;
    result.push({
      date: dateStr,
      legalSlots: MAX_LEGAL_SLOTS,
      legalBooked: booked,
    });
  }

  res.json(result);
});

router.get("/bookings/availability", async (req, res) => {
  const queryResult = AvailabilityQueryParams.safeParse(req.query);
  if (!queryResult.success) {
    res.status(400).json({ error: "Invalid date parameter" });
    return;
  }

  const { date } = queryResult.data;
  const dayOfWeek = new Date(date).getDay();

  if (dayOfWeek === 0) {
    res.json({ date, slots: [] });
    return;
  }

  const bookings = await db
    .select()
    .from(bookingsTable)
    .where(eq(bookingsTable.date, date));

  const bookedTimes = new Set(bookings.map((b) => b.time));

  const slots = TIME_SLOTS.map((time) => ({
    time,
    available: !bookedTimes.has(time),
    booked: bookedTimes.has(time),
  }));

  res.json({ date, slots });
});

router.post("/bookings", async (req, res) => {
  const bodyResult = CreateBookingBodySchema.safeParse(req.body);
  if (!bodyResult.success) {
    res.status(400).json({ error: "Invalid request body" });
    return;
  }

  const { date, time, name, email, phone, notes } = bodyResult.data;

  const dayOfWeek = new Date(date).getDay();
  if (dayOfWeek === 0) {
    res.status(400).json({ error: "日曜日は定休日です" });
    return;
  }

  const existing = await db
    .select()
    .from(bookingsTable)
    .where(
      and(eq(bookingsTable.date, date), eq(bookingsTable.time, time))
    );

  if (existing.length > 0) {
    res.status(400).json({ error: "この時間帯はすでに予約済みです" });
    return;
  }

  const bookingData: {
    date: string;
    time: string;
    name: string;
    email: string;
    phone?: string;
    notes?: string;
  } = { date, time, name, email };
  if (phone) bookingData.phone = phone;
  if (notes) bookingData.notes = notes;

  const result = await db.insert(bookingsTable).values(bookingData);
  const insertId = Number((result[0] as { insertId: number }).insertId);

  const [booking] = await db
    .select()
    .from(bookingsTable)
    .where(eq(bookingsTable.id, insertId));

  res.status(201).json(booking);
});

export default router;
