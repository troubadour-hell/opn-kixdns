# os-kixdns-community

**os-kixdns-community** 是一个 OPNsense 插件，集成了 [KixDNS](https://github.com/olicesx/kixdns) —— 一个基于 Rust 开发的高性能、异步 DNS 服务器。

该插件允许您直接在 OPNsense 防火墙上运行 KixDNS，提供高级的 DNS 路由、灵活的处理管道和高性能的规则处理能力，可作为 Unbound 或 Dnsmasq 的补充（或替代）。

## 主要特性

*   **高性能**：基于 Rust/Tokio 的零拷贝 UDP 处理和异步 IO。
*   **管道架构**：支持定义多个包含有序规则的处理管道 (Pipelines)。
*   **灵活路由**：根据客户端 IP、域名后缀/正则、监听接口等条件将 DNS 请求路由到不同管道。
*   **高级动作**：支持 TCP/UDP 转发、请求拦截、静态响应、重写 TTL 等多种操作。
*   **Web UI 集成**：完全集成到 OPNsense 服务菜单，配置简单便捷。

## 安装方法

### 方法一：下载预编译包（推荐）

1.  访问本仓库的 [Releases](../../releases) 页面。
2.  下载最新的 `os-kixdns-community-x.x.pkg` 文件。
3.  将文件上传到您的 OPNsense 防火墙（例如通过 SCP）。
4.  在命令行执行安装：

    ```bash
    # 安装软件包
    pkg add ./os-kixdns-community-0.1.pkg
    ```

5.  刷新 OPNsense Web 界面，您应该能在 **服务 (Services) > KixDNS** 看到插件。

### 方法二：源码构建

您可以使用提供的 GitHub Actions 工作流进行构建，或者手动构建：

1.  克隆本仓库。
2.  确保 Linux 环境下安装了 `fpm`，或者在 FreeBSD 环境下使用 OPNsense 开发工具。
3.  运行构建工作流生成软件包。

## 使用指南

1.  导航至 **服务 (Services) > KixDNS**。
2.  **常规设置 (General)**：启用服务并配置监听端口（默认为 UDP/TCP 5353）。
3.  **管道管理 (Pipelines)**：定义您的处理管道（例如 `main`, `adblock`）。
4.  **规则管理 (Rules)**：为管道添加规则（例如“拦截广告”、“转发至 Google”）。
5.  **流量分发 (Selectors)**：配置如何将入站流量路由到特定的管道。
6.  点击 **应用更改 (Apply Changes)** 以启动或重载服务。

## 卸载

如需移除插件：

```bash
pkg delete os-kixdns-community
```

## 许可证

本项目采用 GPL-3.0 许可证 - 详见 [LICENSE](https://github.com/olicesx/kixdns/blob/main/LICENSE) 文件。

---
*Powered by KixDNS Core.*
