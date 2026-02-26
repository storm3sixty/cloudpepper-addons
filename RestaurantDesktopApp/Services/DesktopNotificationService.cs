using System.Media;
using RestaurantDesktopApp.Core.Services;

namespace RestaurantDesktopApp.Services;

public sealed class DesktopNotificationService : INotificationService
{
    public Task NotifyAsync(string title, string body, bool playSound, string? soundPath, CancellationToken ct)
    {
        MessageBox.Show(body, title, MessageBoxButton.OK, MessageBoxImage.Information);
        if (playSound)
        {
            if (!string.IsNullOrWhiteSpace(soundPath) && File.Exists(soundPath))
            {
                using var player = new SoundPlayer(soundPath);
                player.Play();
            }
            else
            {
                SystemSounds.Exclamation.Play();
            }
        }

        return Task.CompletedTask;
    }
}
