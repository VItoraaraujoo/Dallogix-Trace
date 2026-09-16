using Microsoft.Web.WebView2.WinForms;
using Microsoft.Web.WebView2.Core;
using System.Diagnostics;

namespace DallogixTrace;

internal static class Program
{
    [STAThread]
    static void Main()
    {
        ApplicationConfiguration.Initialize();
        Application.Run(new TraceForm());
    }
}

internal sealed class TraceForm : Form
{
    private const string TraceUrl = "http://127.0.0.1:8080";
    private readonly WebView2 web = new() { Dock = DockStyle.Fill };

    public TraceForm()
    {
        Text = "Dallogix Trace";
        WindowState = FormWindowState.Maximized;
        FormBorderStyle = FormBorderStyle.None;
        Controls.Add(web);
        Shown += async (_, _) => await StartAsync();
    }

    private async Task StartAsync()
    {
        var root = AppContext.BaseDirectory;
        var compose = Path.Combine(root, "docker-compose.yml");
        if (!File.Exists(compose)) { ShowFailure("Instalação incompleta: docker-compose.yml não encontrado."); return; }
        try
        {
            Process.Start(new ProcessStartInfo("docker.exe", "compose up -d") { WorkingDirectory = root, UseShellExecute = false, CreateNoWindow = true });
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(3) };
            for (var attempt = 0; attempt < 30; attempt++)
            {
                try
                {
                    using var response = await client.GetAsync($"{TraceUrl}/api/health.php");
                    if (response.IsSuccessStatusCode) break;
                }
                catch { }
                await Task.Delay(2000);
                if (attempt == 29) { ShowFailure("Os serviços locais não responderam. Acione a manutenção."); return; }
            }
            await web.EnsureCoreWebView2Async();
            web.CoreWebView2.Settings.AreDevToolsEnabled = false;
            web.CoreWebView2.Settings.AreDefaultContextMenusEnabled = false;
            web.CoreWebView2.Settings.AreBrowserAcceleratorKeysEnabled = false;
            web.CoreWebView2.Navigate(TraceUrl);
        }
        catch (Exception error) { ShowFailure($"Não foi possível iniciar o Trace. {error.Message}"); }
    }

    private void ShowFailure(string message)
    {
        Controls.Clear();
        Controls.Add(new Label { Dock = DockStyle.Fill, Text = message, TextAlign = ContentAlignment.MiddleCenter, Font = new Font("Segoe UI", 18), ForeColor = Color.White, BackColor = Color.FromArgb(16, 34, 46) });
    }
}
