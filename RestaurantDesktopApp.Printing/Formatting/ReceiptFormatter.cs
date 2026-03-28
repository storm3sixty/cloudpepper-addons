using System.Text;
using RestaurantDesktopApp.Core.Models;

namespace RestaurantDesktopApp.Printing.Formatting;

public sealed class ReceiptFormatter
{
    public string FormatOrder(OrderItem order, AppSettings settings)
    {
        var b = new StringBuilder();
        b.AppendLine(settings.RestaurantHeader);
        b.AppendLine("------------------------------");
        b.AppendLine($"ORDER #{order.Number}");
        b.AppendLine(order.CreatedAt.LocalDateTime.ToString("yyyy-MM-dd HH:mm"));
        b.AppendLine($"Customer: {order.CustomerName}");
        if (!string.IsNullOrWhiteSpace(order.Phone)) b.AppendLine($"Phone: {order.Phone}");
        b.AppendLine("------------------------------");
        foreach (var line in order.Lines)
        {
            b.AppendLine($"{line.Quantity} x {line.Name}  {line.Total:0.00}");
            if (!string.IsNullOrWhiteSpace(line.Meta)) b.AppendLine($"  {line.Meta}");
        }
        b.AppendLine("------------------------------");
        b.AppendLine($"TOTAL: {order.Total:0.00} {order.Currency}");
        if (!string.IsNullOrWhiteSpace(order.Notes)) b.AppendLine($"Notes: {order.Notes}");
        b.AppendLine("\n\n\n");
        return b.ToString();
    }

    public string FormatBooking(BookingItem booking, AppSettings settings)
    {
        var b = new StringBuilder();
        b.AppendLine(settings.RestaurantHeader);
        b.AppendLine("------------------------------");
        b.AppendLine($"BOOKING #{booking.Id}");
        b.AppendLine($"Booked at: {booking.BookingTime.LocalDateTime:yyyy-MM-dd HH:mm}");
        b.AppendLine($"Created: {booking.CreatedAt.LocalDateTime:yyyy-MM-dd HH:mm}");
        b.AppendLine($"Guest: {booking.Name}");
        b.AppendLine($"Party size: {booking.PartySize}");
        if (!string.IsNullOrWhiteSpace(booking.Phone)) b.AppendLine($"Phone: {booking.Phone}");
        if (!string.IsNullOrWhiteSpace(booking.Email)) b.AppendLine($"Email: {booking.Email}");
        if (!string.IsNullOrWhiteSpace(booking.Notes)) b.AppendLine($"Notes: {booking.Notes}");
        b.AppendLine("\n\n\n");
        return b.ToString();
    }
}
