# 文件编号登记系统 - 快速开始指南

## 🚀 3步完成部署

### 步骤1：在Windows准备部署包

1. **安装WSL**（如未安装）
   - 打开PowerShell（管理员）
   - 运行：`wsl --install`
   - 重启电脑
   - 安装Ubuntu或Debian

2. **下载软件包**
   - 双击运行 `download_packages_simple.bat`
   - 等待下载完成

3. **打包部署文件**
   - 将 `packages` 和 `offline_package` 目录一起打包

### 步骤2：传输到银河麒麟系统

将打包的文件传输到银河麒麟系统

### 步骤3：部署

```bash
# 安装软件包
sudo dpkg -i packages/*.deb

# 安装应用
cd offline_package
sudo ./install.sh

# 启动服务
sudo ./start.sh start
```

## 📚 详细文档

- **README_OFFLINE.md** - 完整方案说明
- **WINDOWS_PREPARE_GUIDE.md** - Windows环境准备指南
- **OFFLINE_DEPLOY_GUIDE.md** - 银河麒麟系统部署指南

## 🔑 默认账号

- 超级管理员：superadmin / 123456
- 管理员：admin / 123456
- 普通用户：user1 / 123456
- 普通用户：user2 / 123456

**重要提示：首次登录后请立即修改默认密码！**

## 📝 工具说明

**下载工具：**
- `download_packages_simple.bat` - 简单下载工具（推荐）
- `download_wsl.bat` - WSL下载工具
- `download_packages_windows.bat` - Windows下载工具

**打包工具：**
- `package_offline.bat` - 打包工具

## 💡 常见问题

### Q1：WSL未安装怎么办？

**A：** 
1. 打开PowerShell（管理员）
2. 运行：`wsl --install`
3. 重启电脑
4. 安装Ubuntu或Debian

### Q2：下载失败怎么办？

**A：** 
- 检查网络连接
- 确保使用Ubuntu或Debian发行版
- 尝试更换软件源

### Q3：软件包安装失败怎么办？

**A：** 
```bash
sudo apt-get -f install
```

## 📞 技术支持

如有问题，请参考详细文档：
- README_OFFLINE.md
- WINDOWS_PREPARE_GUIDE.md
- OFFLINE_DEPLOY_GUIDE.md

## 📄 许可证

MIT License
