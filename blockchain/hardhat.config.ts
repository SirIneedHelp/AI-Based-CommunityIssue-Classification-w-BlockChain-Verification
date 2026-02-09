import { HardhatUserConfig } from "hardhat/config";

const config: HardhatUserConfig = {
  solidity: {
    version: "0.8.20",
    settings: {
      evmVersion: "paris" // ✅ compatible with Ganache
    }
  },
  networks: {
    ganache: {
      type: "http",
      url: "http://127.0.0.1:7545",
      accounts: [
        "0x3c451a1481913a3563a836534e59e0128332f7a0463fb4e81066ec512a600dfd"
      ],
    },
  },
};

export default config;
