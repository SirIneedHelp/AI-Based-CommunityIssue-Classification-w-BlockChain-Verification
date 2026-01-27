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
        "0x80d6b5629c313ec6ba692d64821c4d74ea615170485ec132c06995abd91ba147"
      ],
    },
  },
};

export default config;
